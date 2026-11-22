@php
    use App\Filament\Pages\CenterDashboard;
    use App\Filament\Support\CoordinatorCenterModules;

    $user = auth()->user();
    $showCoordinatorBack = $user instanceof \App\Models\User
        && $user->isCenterCoordinator()
        && CoordinatorCenterModules::requestMatches(request(), includeCenterDashboard: false);
@endphp
@if ($showCoordinatorBack)
    <a class="ft-coord-back" href="{{ CenterDashboard::getUrl() }}">Back to Center Dashboard</a>
@endif
