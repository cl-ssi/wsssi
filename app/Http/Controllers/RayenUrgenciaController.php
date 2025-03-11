<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RayenUrgenciaController extends Controller
{
    /**
     * Base URL for the healthcare API
     */
    private const API_BASE_URL = 'https://api.saludenred.cl/api/healthCareCenter/';
    
    /**
     * Query path for emergency admissions
     */
    private const API_QUERY_PATH = '/emergencyAdmissions?fromDate=';
    
    /**
     * Admission status constants
     */
    private const STATUS_WAITING = 1;
    private const STATUS_IN_BOX = [12, 99, 100];
    
    /**
     * Cache time in seconds
     */
    private const CACHE_DURATION = 300; // 5 minutes
    
    /**
     * Get the status of emergency admissions from all configured healthcare centers
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        // Set timezone for consistent date handling
        date_default_timezone_set('America/Santiago');
        $currentDate = date('Ymd');
        
        try {
            // Get healthcare centers from config instead of env directly
            $healthcareCenters = $this->getHealthcareCenters();
            
            // Generate a cache key based on the current date
            $cacheKey = 'emergency_status_' . $currentDate;
            
            // Get data from cache or fetch fresh data if not cached
            $data = Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($healthcareCenters, $currentDate) {
                Log::info('Cache miss: Fetching fresh emergency status data');
                return $this->fetchAllCentersData($healthcareCenters, $currentDate);
            });
            
            $result = [
                'data' => $data,
                'updated' => date('Y-m-d H:i'),
                'cached' => Cache::has($cacheKey) ? 'yes' : 'no'
            ];
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error retrieving emergency status: ' . $e->getMessage());
            return response()->json(['error' => 'Error retrieving data', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get the configured healthcare centers
     *
     * @return array
     * @throws \Exception If healthcare centers configuration is invalid
     */
    private function getHealthcareCenters(): array
    {
        // Better to use config files instead of direct env access
        // But keeping env usage for compatibility
        $centers = json_decode(env('ESTABLECIMIENTOS'), true);
        
        if ($centers === null) {
            throw new \Exception('Invalid or missing ESTABLECIMIENTOS configuration in .env file');
        }
        
        return $centers;
    }
    
    /**
     * Fetch data for all healthcare centers
     *
     * @param array $centers
     * @param string $date
     * @return array
     */
    private function fetchAllCentersData(array $centers, string $date): array
    {
        $data = [];
        
        foreach ($centers as $name => $values) {
            $data[$name] = $this->fetchCenterData($values, $date);
        }
        
        return $data;
    }
    
    /**
     * Fetch data for a single healthcare center
     *
     * @param array $centerConfig
     * @param string $date
     * @return array
     */
    private function fetchCenterData(array $centerConfig, string $date): array
    {
        $defaultResponse = [
            'En espera' => 0,
            'En box' => 0
        ];
        
        try {
            $client = new Client([
                'headers' => ['Authorization' => $centerConfig['token']],
                'timeout' => 10, // Adding a reasonable timeout
                'http_errors' => false
            ]);
            
            $url = self::API_BASE_URL . $centerConfig['id'] . self::API_QUERY_PATH . $date;
            $response = $client->get($url);
            
            if ($response->getStatusCode() !== 200) {
                Log::warning("API returned non-200 status code: {$response->getStatusCode()} for center ID: {$centerConfig['id']}");
                return [
                    'En espera' => 'Error',
                    'En box' => 'Error'
                ];
            }
            
            $admissionData = json_decode($response->getBody(), true);
            if (!is_array($admissionData)) {
                throw new \Exception("Invalid data format received from API");
            }
            
            return $this->processAdmissionData($admissionData);
            
        } catch (GuzzleException $e) {
            Log::error("API request failed for center ID {$centerConfig['id']}: " . $e->getMessage());
            return [
                'En espera' => 'Error',
                'En box' => 'Error'
            ];
        } catch (\Exception $e) {
            Log::error("Error processing data for center ID {$centerConfig['id']}: " . $e->getMessage());
            return [
                'En espera' => 'Error',
                'En box' => 'Error'
            ];
        }
    }
    
    /**
     * Process the admission data to count statuses
     *
     * @param array $admissionData
     * @return array
     */
    private function processAdmissionData(array $admissionData): array
    {
        $result = [
            'En espera' => 0,
            'En box' => 0
        ];
        
        if (empty($admissionData)) {
            return $result;
        }
        
        // Count occurrences of each status
        $statusCounts = array_count_values(array_column($admissionData, 'AdmissionStatus'));
        
        // Count waiting patients
        if (isset($statusCounts[self::STATUS_WAITING])) {
            $result['En espera'] = $statusCounts[self::STATUS_WAITING];
        }
        
        // Count in-box patients
        foreach (self::STATUS_IN_BOX as $boxStatus) {
            if (isset($statusCounts[$boxStatus])) {
                $result['En box'] += $statusCounts[$boxStatus];
            }
        }
        
        return $result;
    }
    
    /**
     * Clear the emergency status cache
     *
     * @return JsonResponse
     */
    public function clearCache(): JsonResponse
    {
        $currentDate = date('Ymd');
        $cacheKey = 'emergency_status_' . $currentDate;
        
        Cache::forget($cacheKey);
        
        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }
}