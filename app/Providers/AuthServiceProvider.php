<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Buckle;
use App\Models\BuckleWidth;
use App\Models\Buyer;
use App\Models\Client;
use App\Models\Colour;
use App\Models\ColourObject;
use App\Models\Design;
use App\Models\EndWidth;
use App\Models\LanguageString;
use App\Models\Leather;
use App\Models\Lining;
use App\Models\LoopBand;
use App\Models\NoteInfoLine1;
use App\Models\NoteInfoLine2;
use App\Models\ProdDesign;
use App\Models\Reinforce;
use App\Models\SiteSetting;
use App\Models\Special;
use App\Models\StampingLong;
use App\Models\StampingShort;
use App\Models\StrapLengthLongPart;
use App\Models\StrapLengthShortPart;
use App\Models\StrapPicture;
use App\Models\ThreadSize;
use App\Models\Tool;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\BucklePolicy;
use App\Policies\BuckleWidthPolicy;
use App\Policies\BuyerPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ColourPolicy;
use App\Policies\ColourObjectPolicy;
use App\Policies\DesignPolicy;
use App\Policies\EndWidthPolicy;
use App\Policies\LanguageStringPolicy;
use App\Policies\LeatherPolicy;
use App\Policies\LiningPolicy;
use App\Policies\LoopBandPolicy;
use App\Policies\NoteInfoLine1Policy;
use App\Policies\NoteInfoLine2Policy;
use App\Policies\PermissionPolicy;
use App\Policies\ProdDesignPolicy;
use App\Policies\ReinforcePolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\SpecialPolicy;
use App\Policies\StampingLongPolicy;
use App\Policies\StampingShortPolicy;
use App\Policies\StrapLengthLongPartPolicy;
use App\Policies\StrapLengthShortPartPolicy;
use App\Policies\StrapPicturePolicy;
use App\Policies\ThreadSizePolicy;
use App\Policies\ToolPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
	protected $policies = [
		User::class => UserPolicy::class,
		Role::class => RolePolicy::class,
		Permission::class => PermissionPolicy::class,
		SiteSetting::class => SettingPolicy::class,
		LanguageString::class => LanguageStringPolicy::class,
		ActivityLog::class => ActivityLogPolicy::class,
		Client::class => ClientPolicy::class,
		Buyer::class => BuyerPolicy::class,
		ColourObject::class => ColourObjectPolicy::class,
		Colour::class => ColourPolicy::class,
		Leather::class => LeatherPolicy::class,
		Lining::class => LiningPolicy::class,
		LoopBand::class => LoopBandPolicy::class,
		Buckle::class => BucklePolicy::class,
		BuckleWidth::class => BuckleWidthPolicy::class,
		ThreadSize::class => ThreadSizePolicy::class,
		EndWidth::class => EndWidthPolicy::class,
		Design::class => DesignPolicy::class,
		Reinforce::class => ReinforcePolicy::class,
		StrapLengthLongPart::class => StrapLengthLongPartPolicy::class,
		StrapLengthShortPart::class => StrapLengthShortPartPolicy::class,
		StampingLong::class => StampingLongPolicy::class,
		StampingShort::class => StampingShortPolicy::class,
		StrapPicture::class => StrapPicturePolicy::class,
		Special::class => SpecialPolicy::class,
		ProdDesign::class => ProdDesignPolicy::class,
		NoteInfoLine1::class => NoteInfoLine1Policy::class,
		NoteInfoLine2::class => NoteInfoLine2Policy::class,
		Tool::class => ToolPolicy::class,
	];

	public function boot(): void
	{
		$this->registerPolicies();

		Gate::before(function ($user, $ability) {
			if (!$user) {
				return null;
			}
			
			try {
				// Check roles directly (more reliable)
				$hasAdminRole = $user->hasRole('Admin') 
					|| $user->hasRole('admin') 
					|| $user->hasRole('Administrator');
				
				// Also check via getRoleNames for compatibility
				$names = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->map(fn ($n) => strtolower($n)) : collect();
				$hasAdminLike = $names->contains(fn ($n) => $n === 'admin' || str_contains($n, 'admin'));
				
				$shouldAllow = $hasAdminRole || $hasAdminLike;
				
				// Always log for viewAny/update abilities
				if ($ability === 'viewAny' || $ability === 'update' || str_contains($ability, 'SiteSetting')) {
					\Log::info('Gate::before check', [
						'ability' => $ability,
						'user_id' => $user->id,
						'hasAdminRole' => $hasAdminRole,
						'hasAdminLike' => $hasAdminLike,
						'role_names' => $names->toArray(),
						'shouldAllow' => $shouldAllow,
						'returning' => $shouldAllow ? 'true' : 'null',
					]);
				}
				
				// Return true for admin users, null otherwise (to continue to policy check)
				return $shouldAllow ? true : null;
			} catch (\Throwable $e) {
				\Log::error('Gate::before error', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				]);
				return null;
			}
		});
	}
}


