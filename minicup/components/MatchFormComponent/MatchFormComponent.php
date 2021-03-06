<?php

namespace Minicup\Components;


use Minicup\Misc\EntitiesReplicatorContainer;
use Minicup\Model\Entity\Category;
use Minicup\Model\Entity\Match;
use Minicup\Model\Manager\MatchManager;
use Minicup\Model\Repository\MatchRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Container;
use Nette\Forms\Controls\SubmitButton;
use Nette\Utils\ArrayHash;

interface IMatchFormComponentFactory
{
    /**
     * @param Category $category
     * @param int      $count
     * @return MatchFormComponent
     */
    public function create(Category $category, $count);
}

class MatchFormComponent extends BaseComponent
{
    /** @var Category */
    private $category;

    /** @var int */
    private $count;

    /** @var MatchRepository */
    private $MR;

    /** @var MatchManager */
    private $MM;

    public function __construct(Category $category,
                                $count,
                                MatchRepository $MR,
                                MatchManager $MM)
    {
        $this->category = $category;
        $this->count = $count;
        $this->MR = $MR;
        $this->MM = $MM;
        parent::__construct();
    }

    public function render()
    {
        $this->template->match = $this->category;
        parent::render();
    }

    public function addMatchClicked(SubmitButton $button)
    {
        $button->parent->createOne();
        $this->redrawControl();
    }


    /**
     * @return Form
     */
    protected function createComponentMatchForm()
    {
        $me = $this;

        $f = $this->formFactory->create();

        /** @var EntitiesReplicatorContainer $matches */
        $matches = $f->addDynamic('matches', function (Container $container, Match $match) use ($me) {
            $container->setCurrentGroup($container->getForm()->addGroup('Zápas', FALSE));
            $home = $container
                ->addText('scoreHome', $match->homeTeam->name)
                ->setDefaultValue($match->scoreHome)
                ->setType('number');
            $home->addCondition(Form::INTEGER);
            $container->addCheckbox('confirm', ' OK');
            $container->addText('time')
                ->setDisabled()
                ->setDefaultValue($match->getOnlineStateName() . ' | ' . $match->matchTerm->day->day->format('j. n.') . ' ' . $match->matchTerm->start->format('G:i'));
            $away = $container
                ->addText('scoreAway', $match->awayTeam->name)
                ->setDefaultValue($match->scoreAway)
                ->setType('number');
            $away->addCondition(Form::INTEGER);

            $home->addConditionOn($away, Form::FILLED)->setRequired();
            $away->addConditionOn($home, Form::FILLED)->setRequired();
        },
            $this->MR->findMatchesByCategory($this->category, MatchRepository::UNCONFIRMED),
            $this->count);

        /** @var SubmitButton $addSubmit */
        $matches->addSubmit('addMatch', 'zobrazit další zápas')
            ->setValidationScope(NULL)
            ->setAttribute('class', 'ajax')
            ->onClick[] = [$this, 'addMatchClicked'];
        $f->addSubmit('submit', 'odeslat');

        $f->onSuccess[] = function (Form $form, ArrayHash $values) {
            /** @var SubmitButton $submitButton */
            $submitButton = $form['submit'];
            if ($submitButton->isSubmittedBy()) {
                foreach ($values['matches'] as $matchId => $matchData) {
                    if (!$matchData['scoreHome'] || !$matchData['scoreAway']) {
                        continue;
                    }
                    if (!$matchData['confirm']) {
                        continue;
                    }
                    /** @var Match $match */
                    $match = $this->MR->get((int)$matchId);
                    $this->MM->confirmMatch($match, $matchData['scoreHome'], $matchData['scoreAway']);
                    $this->presenter->flashMessage('Zápas ' . $match->homeTeam->name . ' vs. ' . $match->awayTeam->name . ' byl úspěšně zpracován.');
                }
                $this->redirect('this');
            }
        };
        return $f;
    }
}