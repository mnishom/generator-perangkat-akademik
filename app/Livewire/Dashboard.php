<?php

namespace App\Livewire;

use App\Core\Modules\ModuleAccess;
use App\Models\AiUsage;
use App\Models\GeneratedDocument;
use App\Models\GenerationSession;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beranda')]
class Dashboard extends Component
{
    public function render(ModuleAccess $access)
    {
        $user = auth()->user();
        $month = AiUsage::where('user_id', $user->id)->where('created_at', '>=', now()->startOfMonth());

        return view('livewire.dashboard', [
            'modules' => $access->accessibleModules($user),
            'recent' => GenerationSession::ownedBy($user)->latest()->limit(5)->get(),
            'stats' => [
                'generates' => (clone $month)->where('kind', 'generate')->count(),
                'documents' => GeneratedDocument::where('user_id', $user->id)->whereNotNull('path')->count(),
                'attention' => GeneratedDocument::where('user_id', $user->id)->latestVersions()->where('status', GeneratedDocument::STATUS_ATTENTION)->count(),
            ],
        ]);
    }
}
