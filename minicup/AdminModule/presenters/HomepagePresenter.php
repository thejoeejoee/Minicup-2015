<?php

namespace Minicup\AdminModule\Presenters;

use Minicup\Components\IPhotoUploadComponentFactory;
use Minicup\Model\Manager\ReorderManager;
use Minicup\Model\Repository\MatchRepository;
use Minicup\Model\Repository\TagRepository;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BaseAdminPresenter
{
    /** @var ReorderManager @inject */
    public $reorder;
    /** @var MatchRepository @inject */
    private $MR;

    /** @var  IPhotoUploadComponentFactory @inject */
    public $PUC;

    /** @var  TagRepository @inject */
    public $TR;

    public function createComponentPhotoUploadComponent()
    {
        return $this->PUC->create();
    }


}
