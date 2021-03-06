<?php

namespace Minicup\Components;


use Minicup\AdminModule\Presenters\BaseAdminPresenter;
use Minicup\Model\Entity\Photo;
use Minicup\Model\Entity\Tag;
use Minicup\Model\Manager\CacheManager;
use Minicup\Model\Manager\PhotoManager;
use Minicup\Model\Repository\PhotoRepository;
use Minicup\Model\Repository\TagRepository;
use Nette\Application\UI\Multiplier;
use Nette\Http\Request;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Utils\Random;

interface IPhotoUploadComponentFactory
{
    /** @return PhotoUploadComponent */
    public function create();
}

class PhotoUploadComponent extends BaseComponent
{
    /** @var int[] */
    public $photos = [];
    /** @var Request */
    private $request;
    /** @var SessionSection */
    private $session;
    /** @var PhotoRepository */
    private $PR;
    /** @var TagRepository */
    private $TR;
    /** @var PhotoManager */
    private $PM;
    /** @var IPhotoEditComponentFactory */
    private $PECF;
    /** @var ITagFormComponentFactory */
    private $TFCF;
    /** @var String */
    private $uploadId;
    /** @var CacheManager */
    private $CM;

    /**
     * @param string                     $wwwPath
     * @param Session                    $session
     * @param Request                    $request
     * @param PhotoRepository            $PR
     * @param TagRepository              $TR
     * @param PhotoManager               $PM
     * @param IPhotoEditComponentFactory $PECF
     * @param ITagFormComponentFactory   $TFCF
     * @param CacheManager               $CM
     */
    public function __construct($wwwPath,
                                Session $session,
                                Request $request,
                                PhotoRepository $PR,
                                TagRepository $TR,
                                PhotoManager $PM,
                                IPhotoEditComponentFactory $PECF,
                                ITagFormComponentFactory $TFCF,
                                CacheManager $CM)
    {
        $this->request = $request;
        $this->session = $session->getSection('photoUpload');
        $this->TFCF = $TFCF;
        $this->TR = $TR;
        $this->PR = $PR;
        $this->PM = $PM;
        $this->PECF = $PECF;
        $this->CM = $CM;
        $uploadId = $this->session['uploadId'];
        if ($uploadId) {
            $this->uploadId = $uploadId;
        } else {
            $this->uploadId = Random::generate(20);
        }
        $this->session['uploadId'] = $this->uploadId;
        $this->photos = (array)$this->session[$this->uploadId];
        parent::__construct();

    }

    public function render()
    {
        $this->template->photos = $this->PR->findByIds($this->photos);
        $this->template->uploadId = $this->uploadId;
        $this->session[$this->uploadId] = $this->photos;
        parent::render();
    }


    public function handleUpload()
    {
        $photos = $this->PM->saveFromUpload($this->request->files, $this->uploadId, $this->request->getPost('author'));
        foreach ($photos as $photo) {
            $this->photos[] = $photo->id;
        }
        $this->redrawControl('photos-list');
    }

    /** Signal for tagging all actually uploaded photos */
    public function handleTagsAll()
    {
        $tags = $this->request->post['tags'];
        if (!$tags) {
            return;
        }

        $photos = isset($this->request->post['photos']) ? $this->request->post['photos'] : [];
        if (!$photos) {
			$photos = $this->photos;
		}
        /** @var Tag[] $tags */
        $tags = $this->TR->findByIds($tags);
        /** @var Photo[] $photos */
        $photos = $this->PR->findByIds($photos);
        foreach ($photos as $photo) {
            foreach ($tags as $tag) {
                if (!in_array($tag, $photo->tags, TRUE)) {
                    $photo->addToTags($tag);
                }
                if ($tag->teamInfo) {
                    $this->CM->cleanByEntity($tag->teamInfo->team);
                }
            }
            $this->PR->persist($photo);
        }
        $this->redrawControl('photos-list');
    }

    /**
     * @return PhotoEditComponent
     */
    protected function createComponentPhotoEditComponent()
    {
        $PECF = $this->PECF;
        $PR = $this->PR;
        $PUC = $this;
        return new Multiplier(function ($id) use ($PECF, $PR, $PUC) {
            /** @var Photo $photo */
            $photo = $PR->get($id);
            $PEC = $PECF->create($photo);
            $PEC->onDelete[] = function (Photo $photo) use ($PUC, $PR) {
                $PUC->photos = array_diff($PUC->photos, [$photo->id]);
                $PUC->redrawControl('photos-list');
            };
            $PEC->onSave[] = function (Photo $photo) use ($PUC) {
                $PUC->photos = array_diff($PUC->photos, [$photo->id]);
                $PUC->redrawControl('photos-list');
            };
            return $PEC;
        });
    }

    /**
     * @return TagFormComponent
     */
    protected function createComponentTagFormComponent()
    {
        /** @var BaseAdminPresenter $presenter */
        $presenter = $this->getPresenter();
        $tagForm = $this->TFCF->create(NULL, $presenter->category->year);
        $tagForm->view = 'small';
        return $tagForm;
    }
}