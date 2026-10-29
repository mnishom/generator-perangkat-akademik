<?php

namespace App\Livewire\Library;

use App\Core\Library\FileLibrary;
use App\Core\Library\FileRoles;
use App\Core\Library\UploadValidator;
use App\Models\LibraryFile;
use App\Services\Audit;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Pustaka Berkas')]
class FileLibraryPage extends Component
{
    use WithFileUploads;

    public $upload;

    public string $role = '';

    public bool $shared = false;

    public string $description = '';

    #[Url]
    public string $filter = '';

    public function mount(FileRoles $roles): void
    {
        $this->role = array_key_first($roles->all()) ?? '';
    }

    public function updatedUpload(FileLibrary $library, FileRoles $roles, UploadValidator $validator): void
    {
        $user = auth()->user();
        if (! $this->upload || ! array_key_exists($this->role, $roles->all())) {
            $this->addError('upload', 'Pilih jenis berkas terlebih dahulu.');

            return;
        }
        if ($error = $validator->check($this->upload, $roles->mimes($this->role))) {
            $this->addError('upload', $error);
            $this->reset('upload');

            return;
        }
        $shared = $this->shared && $user->isAdmin();
        if (! $shared && ! $user->prodi_id) {
            $this->addError('upload', 'Pilih Program Studi Anda terlebih dahulu.');

            return;
        }

        $file = $library->store($this->upload, $this->role, $user, shared: $shared, description: $this->description ?: null);
        if ($shared) {
            Audit::log('library.shared.upload', $file, ['role' => $file->role, 'name' => $file->original_name]);
        }

        $this->reset('upload', 'description');
        $this->dispatch('toast', type: 'success', message: "Berkas \"{$file->original_name}\" tersimpan di pustaka.");
    }

    public function delete(int $id): void
    {
        $file = LibraryFile::findOrFail($id);
        Gate::authorize('delete', $file);
        if ($file->is_shared) {
            Audit::log('library.shared.delete', $file, ['name' => $file->original_name]);
        }
        $file->delete();
        $this->dispatch('toast', type: 'info', message: 'Berkas dihapus dari pustaka.');
    }

    public function render(FileRoles $roles)
    {
        $user = auth()->user();
        $files = LibraryFile::with(['uploader', 'prodi'])
            ->when(! $user->isAdmin(), fn ($q) => $q->availableTo($user))
            ->when($user->isAdmin(), fn ($q) => $q->where(fn ($q) => $q->where('is_shared', true)->orWhere('prodi_id', $user->prodi_id)))
            ->when($this->filter, fn ($q) => $q->where('role', $this->filter))
            ->latest()
            ->get();

        return view('livewire.library.file-library-page', [
            'roles' => $roles->all(),
            'files' => $files->groupBy('role'),
        ]);
    }
}
