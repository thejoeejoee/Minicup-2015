<?php

namespace Minicup\FrontModule\Presenters;

use Minicup\Components\IOnlineReportComponentFactory,
    Minicup\Model\Repository\MatchRepository;

/**
 * Homepage presenter.
 */
class HomepagePresenter extends BasePresenter {

    /** @var \Minicup\Components\IOnlineReportComponentFactory */
    private $ORCFactory;

    /** @var \Minicup\Model\Repository\MatchRepository */
    private $MR;

    public function __construct(IOnlineReportComponentFactory $ORCFactory,
            MatchRepository $MR) {
        parent::__construct();
        $this->ORCFactory = $ORCFactory;
        $this->MR = $MR;
    }

    public function renderDefault() {
        $match = $this->MR->find(4);
        $this->template->match = $match;
    }
    public function actionDefault() {
        // TODO: move to OnlineReportComponent factory
        $match = $this->MR->find(4);
        $this['onlineReportComponent']->match = $match;
    }

    public function createComponentOnlineReportComponent() {
        return $this->ORCFactory->create();
    }

}
