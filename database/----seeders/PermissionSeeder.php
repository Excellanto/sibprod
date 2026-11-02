<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Admin panel
            'admin-panel-access',
            // User Management
            'user-management.show-in-sidebar',
            // Client Buyer Management
            'client-buyer-management.show-in-sidebar',
            // Production Data Management
            'production-data-management.show-in-sidebar',
            // Order Management
            'order-management.show-in-sidebar',
            // Users
            'users.show-in-sidebar',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            // Roles
            'roles.show-in-sidebar',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            // Permissions
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            // Settings
            'settings.show-in-sidebar',
            'settings.view',
            'settings.update',
            // App Configuration
            'app-configuration.show-in-sidebar',
            'app-configuration.view',
            'app-configuration.update',
            // App Data Values
            'app-data-values.show-in-sidebar',
            'app-data-values.view',
            'app-data-values.create',
            'app-data-values.update',
            'app-data-values.delete',
            // Company Details
            'company-details.show-in-sidebar',
            'company-details.view',
            'company-details.update',
            // Languages
            'languages.show-in-sidebar',
            'languages.view',
            'languages.update',
            'languages.sync',
            // Activity Logs
            'activity-logs.show-in-sidebar',
            'activity-logs.view',
            'activity-logs.export',
            'activity-logs.delete',
            // Clients
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',
            // Buyers
            'buyers.view',
            'buyers.create',
            'buyers.update',
            'buyers.delete',
            // Production Data - Colour Object
            'colour-object.view',
            'colour-object.create',
            'colour-object.update',
            'colour-object.delete',
            // Production Data - Colours
            'colours.view',
            'colours.create',
            'colours.update',
            'colours.delete',
            // Production Data - Leather
            'leather.view',
            'leather.create',
            'leather.update',
            'leather.delete',
            // Production Data - Lining
            'lining.view',
            'lining.create',
            'lining.update',
            'lining.delete',
            // Production Data - Loop Band
            'loop-band.view',
            'loop-band.create',
            'loop-band.update',
            'loop-band.delete',
            // Production Data - Buckle
            'buckle.view',
            'buckle.create',
            'buckle.update',
            'buckle.delete',
            // Production Data - Buckle Width
            'buckle-width.view',
            'buckle-width.create',
            'buckle-width.update',
            'buckle-width.delete',
            // Production Data - Thread Size
            'thread-size.view',
            'thread-size.create',
            'thread-size.update',
            'thread-size.delete',
            // Production Data - End Width
            'end-width.view',
            'end-width.create',
            'end-width.update',
            'end-width.delete',
            // Production Data - Design Name
            'design-name.view',
            'design-name.create',
            'design-name.update',
            'design-name.delete',
            // Production Data - Design Description
            'design-description.view',
            'design-description.create',
            'design-description.update',
            'design-description.delete',
            // Production Data - Reinforce
            'reinforce.view',
            'reinforce.create',
            'reinforce.update',
            'reinforce.delete',
            // Production Data - Strap Length Long Part
            'strap-length-long-part.view',
            'strap-length-long-part.create',
            'strap-length-long-part.update',
            'strap-length-long-part.delete',
            // Production Data - Strap Length Short Part
            'strap-length-short-part.view',
            'strap-length-short-part.create',
            'strap-length-short-part.update',
            'strap-length-short-part.delete',
            // Production Data - Stamping Long
            'stamping-long.view',
            'stamping-long.create',
            'stamping-long.update',
            'stamping-long.delete',
            // Production Data - Stamping Short
            'stamping-short.view',
            'stamping-short.create',
            'stamping-short.update',
            'stamping-short.delete',
            // Production Data - Strap Pictures
            'strap-pictures.view',
            'strap-pictures.create',
            'strap-pictures.update',
            'strap-pictures.delete',
            // Production Data - Special
            'special.view',
            'special.create',
            'special.update',
            'special.delete',
            // Production Data - Production Design
            'prod-design.view',
            'prod-design.create',
            'prod-design.update',
            'prod-design.delete',
            // Production Data - Note Info Line1
            'note-info-line1.view',
            'note-info-line1.create',
            'note-info-line1.update',
            'note-info-line1.delete',
            // Production Data - Note Info Line2
            'note-info-line2.view',
            'note-info-line2.create',
            'note-info-line2.update',
            'note-info-line2.delete',
            // Production Data - Tools
            'tools.view',
            'tools.create',
            'tools.update',
            'tools.delete',
            // Order Management - Process Order
            'process-order.view',
            'process-order.create',
            'process-order.update',
            'process-order.delete',
            'process-order.show-in-sidebar',
            // Order Management - Model Template
            'model-template.view',
            'model-template.create',
            'model-template.update',
            'model-template.delete',
            'model-template.show-in-sidebar',
            // Order Management - Production Orders
            'production-orders.view',
            'production-orders.create',
            'production-orders.update',
            'production-orders.delete',
            'production-orders.show-in-sidebar',
            // Order Management - Quality Control
            'quality-control.view',
            'quality-control.create',
            'quality-control.update',
            'quality-control.delete',
            'quality-control.show-in-sidebar',
            // Order Management - Packaging
            'packaging.view',
            'packaging.create',
            'packaging.update',
            'packaging.delete',
            'packaging.show-in-sidebar',
            // Invoice Management
            'invoice-management.view',
            'invoice-management.create',
            'invoice-management.update',
            'invoice-management.delete',
            'invoice-management.show-in-sidebar',
            // Reports
            'reports.view',
            'reports.show-in-sidebar',
            'reports.export',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
