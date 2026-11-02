<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppDataValuesController extends Controller
{
    /**
     * Display OrderPos Status index page
     */
    public function orderPosStatusIndex(): Response
    {
        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // If no data exists, initialize with default values
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Newly Added'],
                ['id' => 2, 'name' => 'In Production'],
                ['id' => 3, 'name' => 'Produced'],
            ];
            // Save defaults
            SiteSetting::updateOrCreate(
                ['key' => 'orderpos_status'],
                ['value' => json_encode($statuses), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/AppDataValues/OrderPosStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a new OrderPos Status
     */
    public function orderPosStatusStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Generate new ID
        $maxId = 0;
        foreach ($statuses as $status) {
            if (isset($status['id']) && $status['id'] > $maxId) {
                $maxId = $status['id'];
            }
        }
        $newId = $maxId + 1;

        // Add new status
        $statuses[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        // Save to site_settings
        SiteSetting::updateOrCreate(
            ['key' => 'orderpos_status'],
            ['value' => json_encode($statuses), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orderpos-status.index')
            ->with('success', 'OrderPos Status added successfully.');
    }

    /**
     * Update an existing OrderPos Status
     */
    public function orderPosStatusUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.orderpos-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.orderpos-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Find and update the status
        $found = false;
        foreach ($statuses as &$status) {
            if (isset($status['id']) && $status['id'] == $id) {
                $status['name'] = $validated['name'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.orderpos-status.index')
                ->with('error', 'Status not found.');
        }

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orderpos-status.index')
            ->with('success', 'OrderPos Status updated successfully.');
    }

    /**
     * Delete an OrderPos Status
     */
    public function orderPosStatusDestroy($id)
    {
        $setting = SiteSetting::where('key', 'orderpos_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.orderpos-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.orderpos-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Remove the status
        $statuses = array_filter($statuses, function ($status) use ($id) {
            return ! isset($status['id']) || $status['id'] != $id;
        });
        $statuses = array_values($statuses); // Re-index array

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orderpos-status.index')
            ->with('success', 'OrderPos Status deleted successfully.');
    }

    /**
     * Display Model Status index page
     */
    public function modelStatusIndex(): Response
    {
        $setting = SiteSetting::where('key', 'model_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // If no data exists, initialize with default values
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'New Added'],
                ['id' => 2, 'name' => 'Matched'],
            ];
            // Save defaults
            SiteSetting::updateOrCreate(
                ['key' => 'model_status'],
                ['value' => json_encode($statuses), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/AppDataValues/ModelStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a new Model Status
     */
    public function modelStatusStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'model_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Generate new ID
        $maxId = 0;
        foreach ($statuses as $status) {
            if (isset($status['id']) && $status['id'] > $maxId) {
                $maxId = $status['id'];
            }
        }
        $newId = $maxId + 1;

        // Add new status
        $statuses[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        // Save to site_settings
        SiteSetting::updateOrCreate(
            ['key' => 'model_status'],
            ['value' => json_encode($statuses), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.model-status.index')
            ->with('success', 'Model Status added successfully.');
    }

    /**
     * Update an existing Model Status
     */
    public function modelStatusUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'model_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.model-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.model-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Find and update the status
        $found = false;
        foreach ($statuses as &$status) {
            if (isset($status['id']) && $status['id'] == $id) {
                $status['name'] = $validated['name'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.model-status.index')
                ->with('error', 'Status not found.');
        }

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.model-status.index')
            ->with('success', 'Model Status updated successfully.');
    }

    /**
     * Delete a Model Status
     */
    public function modelStatusDestroy($id)
    {
        $setting = SiteSetting::where('key', 'model_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.model-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.model-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Remove the status
        $statuses = array_filter($statuses, function ($status) use ($id) {
            return ! isset($status['id']) || $status['id'] != $id;
        });
        $statuses = array_values($statuses); // Re-index array

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.model-status.index')
            ->with('success', 'Model Status deleted successfully.');
    }

    /**
     * Display Orders Status index page
     */
    public function ordersStatusIndex(): Response
    {
        $setting = SiteSetting::where('key', 'orders_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Return exactly what's in site_settings, no modifications or migrations
        // Users manage these values through the App Data Values interface

        return Inertia::render('Admin/AppDataValues/OrdersStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a new Orders Status
     */
    public function ordersStatusStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'orders_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Generate new ID
        $maxId = 0;
        foreach ($statuses as $status) {
            if (isset($status['id']) && $status['id'] > $maxId) {
                $maxId = $status['id'];
            }
        }
        $newId = $maxId + 1;

        // Add new status
        $statuses[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        // Save to site_settings
        SiteSetting::updateOrCreate(
            ['key' => 'orders_status'],
            ['value' => json_encode($statuses), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orders-status.index')
            ->with('success', 'Orders Status added successfully.');
    }

    /**
     * Update an existing Orders Status
     */
    public function ordersStatusUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'orders_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.orders-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.orders-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Find and update the status
        $found = false;
        foreach ($statuses as &$status) {
            if (isset($status['id']) && $status['id'] == $id) {
                $status['name'] = $validated['name'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.orders-status.index')
                ->with('error', 'Status not found.');
        }

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orders-status.index')
            ->with('success', 'Orders Status updated successfully.');
    }

    /**
     * Delete an Orders Status
     */
    public function ordersStatusDestroy($id)
    {
        $setting = SiteSetting::where('key', 'orders_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.orders-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.orders-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Remove the status
        $statuses = array_filter($statuses, function ($status) use ($id) {
            return ! isset($status['id']) || $status['id'] != $id;
        });
        $statuses = array_values($statuses); // Re-index array

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.orders-status.index')
            ->with('success', 'Orders Status deleted successfully.');
    }

    /**
     * Display Quality Control Status index page
     */
    public function qualityControlStatusIndex(): Response
    {
        $setting = SiteSetting::where('key', 'quality_control_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // If no data exists, initialize with default values
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Pending'],
                ['id' => 2, 'name' => 'In Progress'],
                ['id' => 3, 'name' => 'Completed'],
            ];
            // Save defaults
            SiteSetting::updateOrCreate(
                ['key' => 'quality_control_status'],
                ['value' => json_encode($statuses), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/AppDataValues/QualityControlStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a new Quality Control Status
     */
    public function qualityControlStatusStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'quality_control_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Generate new ID
        $maxId = 0;
        foreach ($statuses as $status) {
            if (isset($status['id']) && $status['id'] > $maxId) {
                $maxId = $status['id'];
            }
        }
        $newId = $maxId + 1;

        // Add new status
        $statuses[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        // Save to site_settings
        SiteSetting::updateOrCreate(
            ['key' => 'quality_control_status'],
            ['value' => json_encode($statuses), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.quality-control-status.index')
            ->with('success', 'Quality Control Status added successfully.');
    }

    /**
     * Update an existing Quality Control Status
     */
    public function qualityControlStatusUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'quality_control_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.quality-control-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.quality-control-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Find and update the status
        $found = false;
        foreach ($statuses as &$status) {
            if (isset($status['id']) && $status['id'] == $id) {
                $status['name'] = $validated['name'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.quality-control-status.index')
                ->with('error', 'Status not found.');
        }

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.quality-control-status.index')
            ->with('success', 'Quality Control Status updated successfully.');
    }

    /**
     * Delete a Quality Control Status
     */
    public function qualityControlStatusDestroy($id)
    {
        $setting = SiteSetting::where('key', 'quality_control_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.quality-control-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.quality-control-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Remove the status
        $statuses = array_filter($statuses, function ($status) use ($id) {
            return ! isset($status['id']) || $status['id'] != $id;
        });
        $statuses = array_values($statuses);

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.quality-control-status.index')
            ->with('success', 'Quality Control Status deleted successfully.');
    }

    /**
     * Display Packaging Status index page
     */
    public function packagingStatusIndex(): Response
    {
        $setting = SiteSetting::where('key', 'packaging_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // If no data exists, initialize with default values
        if (empty($statuses)) {
            $statuses = [
                ['id' => 1, 'name' => 'Created'],
                ['id' => 2, 'name' => 'In Process'],
                ['id' => 3, 'name' => 'Done'],
            ];
            // Save defaults
            SiteSetting::updateOrCreate(
                ['key' => 'packaging_status'],
                ['value' => json_encode($statuses), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/AppDataValues/PackagingStatus/Index', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a new Packaging Status
     */
    public function packagingStatusStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'packaging_status')->first();
        $statuses = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $statuses = $decoded;
            }
        }

        // Generate new ID
        $maxId = 0;
        foreach ($statuses as $status) {
            if (isset($status['id']) && $status['id'] > $maxId) {
                $maxId = $status['id'];
            }
        }
        $newId = $maxId + 1;

        // Add new status
        $statuses[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        // Save to site_settings
        SiteSetting::updateOrCreate(
            ['key' => 'packaging_status'],
            ['value' => json_encode($statuses), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.packaging-status.index')
            ->with('success', 'Packaging Status added successfully.');
    }

    /**
     * Update an existing Packaging Status
     */
    public function packagingStatusUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'packaging_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.packaging-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.packaging-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Find and update the status
        $found = false;
        foreach ($statuses as &$status) {
            if (isset($status['id']) && $status['id'] == $id) {
                $status['name'] = $validated['name'];
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.packaging-status.index')
                ->with('error', 'Status not found.');
        }

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.packaging-status.index')
            ->with('success', 'Packaging Status updated successfully.');
    }

    /**
     * Delete a Packaging Status
     */
    public function packagingStatusDestroy($id)
    {
        $setting = SiteSetting::where('key', 'packaging_status')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.packaging-status.index')
                ->with('error', 'Status not found.');
        }

        $statuses = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($statuses)) {
            return redirect()->route('admin.app-data-values.packaging-status.index')
                ->with('error', 'Invalid status data.');
        }

        // Remove the status
        $statuses = array_filter($statuses, function ($status) use ($id) {
            return ! isset($status['id']) || $status['id'] != $id;
        });
        $statuses = array_values($statuses);

        // Save updated statuses
        $setting->value = json_encode($statuses);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.packaging-status.index')
            ->with('success', 'Packaging Status deleted successfully.');
    }

    /**
     * Display QC Reasons index page
     */
    public function qcReasonsIndex(): Response
    {
        $setting = SiteSetting::where('key', 'qc_reasons')->first();
        $reasons = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reasons = $decoded;
            }
        }

        return Inertia::render('Admin/AppDataValues/QcReasons/Index', [
            'reasons' => $reasons,
        ]);
    }

    /**
     * Store a new QC Reason
     */
    public function qcReasonsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'qc_reasons')->first();
        $reasons = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reasons = $decoded;
            }
        }

        $maxId = 0;
        foreach ($reasons as $r) {
            if (isset($r['id']) && $r['id'] > $maxId) {
                $maxId = $r['id'];
            }
        }
        $newId = $maxId + 1;

        $reasons[] = [
            'id' => $newId,
            'name' => $validated['name'],
        ];

        SiteSetting::updateOrCreate(
            ['key' => 'qc_reasons'],
            ['value' => json_encode($reasons), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.qc-reasons.index')
            ->with('success', 'QC Reason added successfully.');
    }

    /**
     * Update an existing QC Reason
     */
    public function qcReasonsUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'qc_reasons')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.qc-reasons.index')
                ->with('error', 'Reason not found.');
        }

        $reasons = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($reasons)) {
            return redirect()->route('admin.app-data-values.qc-reasons.index')
                ->with('error', 'Invalid data.');
        }

        $found = false;
        foreach ($reasons as &$r) {
            if (isset($r['id']) && (int) $r['id'] === (int) $id) {
                $r['name'] = $validated['name'];
                unset($r['type']);
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.qc-reasons.index')
                ->with('error', 'Reason not found.');
        }

        $setting->value = json_encode($reasons);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.qc-reasons.index')
            ->with('success', 'QC Reason updated successfully.');
    }

    /**
     * Delete a QC Reason
     */
    public function qcReasonsDestroy($id)
    {
        $setting = SiteSetting::where('key', 'qc_reasons')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.qc-reasons.index')
                ->with('error', 'Reason not found.');
        }

        $reasons = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($reasons)) {
            return redirect()->route('admin.app-data-values.qc-reasons.index')
                ->with('error', 'Invalid data.');
        }

        $reasons = array_values(array_filter($reasons, fn ($r) => ! isset($r['id']) || (int) $r['id'] !== (int) $id));

        $setting->value = json_encode($reasons);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.qc-reasons.index')
            ->with('success', 'QC Reason deleted successfully.');
    }

    /**
     * Display Line Item Desc index page
     */
    public function lineItemDescIndex(): Response
    {
        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        $items = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        // If no data exists, initialize with default value
        if (empty($items)) {
            $items = [
                ['id' => 1, 'name' => 'LEATHER WATCH STRAPS WITH BUCKLES'],
            ];
            // Save defaults
            SiteSetting::updateOrCreate(
                ['key' => 'line_item_desc'],
                ['value' => json_encode($items), 'type' => 'json']
            );
            Cache::forget('app_settings');
        }

        return Inertia::render('Admin/AppDataValues/LineItemDesc/Index', [
            'items' => $items,
        ]);
    }

    /**
     * Store a new Line Item Desc
     */
    public function lineItemDescStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        $items = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        $name = trim($validated['name']);
        if (json_setting_items_name_is_duplicate($items, $name, null)) {
            throw ValidationException::withMessages([
                'name' => ['This name already exists.'],
            ]);
        }

        $maxId = 0;
        foreach ($items as $item) {
            if (isset($item['id']) && $item['id'] > $maxId) {
                $maxId = $item['id'];
            }
        }
        $newId = $maxId + 1;

        $items[] = [
            'id' => $newId,
            'name' => $name,
        ];

        SiteSetting::updateOrCreate(
            ['key' => 'line_item_desc'],
            ['value' => json_encode($items), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.line-item-desc.index')
            ->with('success', 'Line Item Desc added successfully.');
    }

    /**
     * Update an existing Line Item Desc
     */
    public function lineItemDescUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.line-item-desc.index')
                ->with('error', 'Item not found.');
        }

        $items = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($items)) {
            return redirect()->route('admin.app-data-values.line-item-desc.index')
                ->with('error', 'Invalid data.');
        }

        $name = trim($validated['name']);
        if (json_setting_items_name_is_duplicate($items, $name, (int) $id)) {
            throw ValidationException::withMessages([
                'name' => ['This name already exists.'],
            ]);
        }

        $found = false;
        foreach ($items as &$item) {
            if (isset($item['id']) && (int) $item['id'] === (int) $id) {
                $item['name'] = $name;
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.line-item-desc.index')
                ->with('error', 'Item not found.');
        }

        $setting->value = json_encode($items);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.line-item-desc.index')
            ->with('success', 'Line Item Desc updated successfully.');
    }

    /**
     * Delete a Line Item Desc
     */
    public function lineItemDescDestroy($id)
    {
        $setting = SiteSetting::where('key', 'line_item_desc')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.line-item-desc.index')
                ->with('error', 'Item not found.');
        }

        $items = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($items)) {
            return redirect()->route('admin.app-data-values.line-item-desc.index')
                ->with('error', 'Invalid data.');
        }

        $items = array_values(array_filter($items, fn ($item) => ! isset($item['id']) || (int) $item['id'] !== (int) $id));

        $setting->value = json_encode($items);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.line-item-desc.index')
            ->with('success', 'Line Item Desc deleted successfully.');
    }

    /**
     * Display PO Tags index page
     */
    public function poTagsIndex(): Response
    {
        $setting = SiteSetting::where('key', 'po_tags')->first();
        $items = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        return Inertia::render('Admin/AppDataValues/POTags/Index', [
            'items' => $items,
        ]);
    }

    /**
     * Store a new PO Tag
     */
    public function poTagsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'po_tags')->first();
        $items = [];

        if ($setting && $setting->type === 'json') {
            $decoded = json_decode($setting->value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            }
        }

        $name = trim($validated['name']);
        if (json_setting_items_name_is_duplicate($items, $name, null)) {
            throw ValidationException::withMessages([
                'name' => ['This name already exists.'],
            ]);
        }

        $maxId = 0;
        foreach ($items as $item) {
            if (isset($item['id']) && $item['id'] > $maxId) {
                $maxId = $item['id'];
            }
        }
        $newId = $maxId + 1;

        $items[] = [
            'id' => $newId,
            'name' => $name,
        ];

        SiteSetting::updateOrCreate(
            ['key' => 'po_tags'],
            ['value' => json_encode($items), 'type' => 'json']
        );

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.po-tags.index')
            ->with('success', 'PO Tag added successfully.');
    }

    /**
     * Update an existing PO Tag
     */
    public function poTagsUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $setting = SiteSetting::where('key', 'po_tags')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.po-tags.index')
                ->with('error', 'Item not found.');
        }

        $items = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($items)) {
            return redirect()->route('admin.app-data-values.po-tags.index')
                ->with('error', 'Invalid data.');
        }

        $name = trim($validated['name']);
        if (json_setting_items_name_is_duplicate($items, $name, (int) $id)) {
            throw ValidationException::withMessages([
                'name' => ['This name already exists.'],
            ]);
        }

        $found = false;
        foreach ($items as &$item) {
            if (isset($item['id']) && (int) $item['id'] === (int) $id) {
                $item['name'] = $name;
                $found = true;
                break;
            }
        }

        if (! $found) {
            return redirect()->route('admin.app-data-values.po-tags.index')
                ->with('error', 'Item not found.');
        }

        $setting->value = json_encode($items);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.po-tags.index')
            ->with('success', 'PO Tag updated successfully.');
    }

    /**
     * Delete a PO Tag
     */
    public function poTagsDestroy($id)
    {
        $setting = SiteSetting::where('key', 'po_tags')->first();
        if (! $setting || $setting->type !== 'json') {
            return redirect()->route('admin.app-data-values.po-tags.index')
                ->with('error', 'Item not found.');
        }

        $items = json_decode($setting->value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($items)) {
            return redirect()->route('admin.app-data-values.po-tags.index')
                ->with('error', 'Invalid data.');
        }

        $items = array_values(array_filter($items, fn ($item) => ! isset($item['id']) || (int) $item['id'] !== (int) $id));

        $setting->value = json_encode($items);
        $setting->save();

        Cache::forget('app_settings');

        return redirect()->route('admin.app-data-values.po-tags.index')
            ->with('success', 'PO Tag deleted successfully.');
    }
}
