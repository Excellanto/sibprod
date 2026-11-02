<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CompanyDetailsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Check authorization - admin users or users with company-details.view permission
        if (! $user->hasRole('Admin') && ! $user->hasRole('admin') && ! $user->hasRole('Administrator') && ! $user->can('company-details.view')) {
            abort(403, 'This action is unauthorized.');
        }

        $settings = SiteSetting::whereIn('key', [
            'company.name',
            'company.address',
            'company.exporter_ref',
            'company.iec_no',
            'company.pan_no',
            'company.hsn',
            'company.terms_of_delivery_and_payments',
            'company.features',
            'company.export_statement',
        ])->pluck('value', 'key')->toArray();

        // Initialize defaults if not set

        return Inertia::render('Admin/CompanyDetails/Index', [
            'name' => $settings['company.name'] ?? 'SIBRAMA NEWAGE',
            'address' => $settings['company.address'] ?? '',
            'exporterRef' => $settings['company.exporter_ref'] ?? 'DS - 011745',
            'iecNo' => $settings['company.iec_no'] ?? '4101000042',
            'panNo' => $settings['company.pan_no'] ?? 'AAEFS5453M',
            'hsn' => $settings['company.hsn'] ?? '9113 90 90',
            'termsOfDeliveryAndPayments' => $settings['company.terms_of_delivery_and_payments'] ?? '',
            'features' => $settings['company.features'] ?? '',
            'exportStatement' => $settings['company.export_statement'] ?? company_default_export_statement_plain(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        // Check authorization - admin users or users with company-details.update permission
        if (! $user->hasRole('Admin') && ! $user->hasRole('admin') && ! $user->hasRole('Administrator') && ! $user->can('company-details.update')) {
            abort(403, 'This action is unauthorized.');
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'exporterRef' => ['nullable', 'string', 'max:255'],
            'iecNo' => ['nullable', 'string', 'max:255'],
            'panNo' => ['nullable', 'string', 'max:255'],
            'hsn' => ['nullable', 'string', 'max:255'],
            'termsOfDeliveryAndPayments' => ['nullable', 'string'],
            'features' => ['nullable', 'string'],
            'exportStatement' => ['nullable', 'string', 'max:10000'],
        ]);

        // Clear cache before update
        Cache::forget('app_settings');

        // Update company settings
        SiteSetting::updateOrCreate(
            ['key' => 'company.name'],
            ['value' => $data['name'] ?? 'SIBRAMA NEWAGE', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.address'],
            ['value' => $data['address'] ?? '', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.exporter_ref'],
            ['value' => $data['exporterRef'] ?? 'DS - 011745', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.iec_no'],
            ['value' => $data['iecNo'] ?? '4101000042', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.pan_no'],
            ['value' => $data['panNo'] ?? 'AAEFS5453M', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.hsn'],
            ['value' => $data['hsn'] ?? '9113 90 90', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.terms_of_delivery_and_payments'],
            ['value' => $data['termsOfDeliveryAndPayments'] ?? '', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.features'],
            ['value' => $data['features'] ?? '', 'type' => 'string']
        );

        SiteSetting::updateOrCreate(
            ['key' => 'company.export_statement'],
            ['value' => $data['exportStatement'] ?? company_default_export_statement_plain(), 'type' => 'string']
        );

        // Ensure cache is cleared
        Cache::forget('app_settings');

        return redirect()->route('admin.company-details.index')->with('success', 'Company details updated successfully.');
    }
}
