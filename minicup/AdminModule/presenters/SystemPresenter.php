<?php

namespace Minicup\AdminModule\Presenters;

use Minicup\Model\Manager\PhotoManager;
use Nette;

/**
 * Cache presenter.
 */
class SystemPresenter extends BaseAdminPresenter
{
    /** @var PhotoManager @inject */
    public $PM;

    public function handleDeleteAllEntityCaches()
    {
        $this->CM->cleanAllEntityCaches();
        $this->flashMessage('Cache všech entity aplikace byly úspěšně promazány.', 'success');
    }

    public function handleDeleteAllCachedPhoto()
    {
        $failed = $this->PM->cleanCachedPhotos();
        if (!$failed) {
            $this->flashMessage('Všechny fotky v cache byly promazány!', 'success');
        } else {
            $this->flashMessage('Promazání fotek ' . join(', ', $failed) . 'selhalo, zbytek v pořádku.', 'warning');
        }
    }

    public function handleDeleteLatteCaches()
    {
        $latte = $this->context->parameters['tempDir'] . '/cache/latte';
        if (file_exists($latte)) {
            if ($this->rmDir($latte)) {
                $this->flashMessage('Latte cache promazány', 'success');
            } else {
                $this->flashMessage('Selhalo promazání latte cache.', 'danger');
            }
        }
    }

    public function handleDeleteTemp()
    {
        $cache = $this->context->parameters['tempDir'] . '/cache';
        if ($this->rmDir($cache)) {
            mkdir($cache);
            $this->flashMessage('Obsah temp smazán!', 'success');
        } else {
            $this->flashMessage('Něco se pokazilo při mazání tempu.', 'danger');
        }
    }

    private static function rmDir($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? static::rmDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
