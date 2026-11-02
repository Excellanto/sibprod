<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log authenticated users and successful responses
        if (Auth::check() && $response->isSuccessful()) {
            $this->logRequest($request, $response);
        }

        return $response;
    }

    /**
     * Log the request based on route patterns.
     */
    protected function logRequest(Request $request, Response $response): void
    {
        $method = $request->method();
        $path = $request->path();

        // Skip logging for certain routes
        if ($this->shouldSkipLogging($path)) {
            return;
        }

        // Determine module and action from route
        $logData = $this->determineLogData($method, $path, $request);

        if ($logData) {
            $this->activityLogService->log(
                $logData['module'],
                $logData['action'],
                $logData['description'],
                $logData['options'] ?? []
            );
        }
    }

    /**
     * Determine if logging should be skipped for this path.
     */
    protected function shouldSkipLogging(string $path): bool
    {
        $skipPatterns = [
            'api/activity-logs', // Don't log viewing activity logs
            'sanctum/csrf-cookie',
            '_ignition',
            'telescope',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine log data from route.
     */
    protected function determineLogData(string $method, string $path, Request $request): ?array
    {
        // Parse common patterns
        // Example: admin/users/123 -> module: users, action: view/edit/delete based on method
        
        if (preg_match('/admin\/(\w+)/', $path, $matches)) {
            $module = $matches[1];
            
            return [
                'module' => $module,
                'action' => $this->getActionFromMethod($method),
                'description' => $this->generateDescription($method, $module),
                'options' => [],
            ];
        }

        return null;
    }

    /**
     * Get action from HTTP method.
     */
    protected function getActionFromMethod(string $method): string
    {
        return match ($method) {
            'GET' => 'viewed',
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'accessed',
        };
    }

    /**
     * Generate description.
     */
    protected function generateDescription(string $method, string $module): string
    {
        $action = $this->getActionFromMethod($method);
        return ucfirst($action) . ' ' . $module;
    }
}

