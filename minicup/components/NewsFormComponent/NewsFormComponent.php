<?php

namespace Minicup\Components;


use Countable;
use Dibi\DateTime;
use Dibi\DriverException;
use Minicup\Model\Entity\News;
use Minicup\Model\Manager\TagManager;
use Minicup\Model\Repository\NewsRepository;
use Minicup\Model\Repository\YearRepository;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

interface INewsFormComponentFactory
{
    /**
     * @param News $news
     * @return NewsFormComponent
     */
    public function create(News $news = NULL);
}

class NewsFormComponent extends BaseComponent
{
    /** @var NewsRepository */
    private $NR;

    /** @var YearRepository */
    private $YR;

    /** @var News */
    private $news;

    /** @var TagManager */
    private $tagManager;

    public function __construct(News $news = NULL,
                                NewsRepository $NR,
                                YearRepository $yearRepository,
                                TagManager $tagManager)
    {
        $this->news = $news;
        $this->NR = $NR;
        $this->YR = $yearRepository;

        $this->tagManager = $tagManager;
        parent::__construct();
    }

    public function render()
    {
        $this->template->news = $this->news;
        if ($this->news) {
            /** @var Form $form */
            $form = $this['newsForm'];
            $form->setDefaults($this->news->getData(['title', 'content', 'id', 'texy', 'published']));
            $form->setDefaults(['year' => $this->news->year->id]);
        }
        parent::render();
    }

    /**
     * @return Form
     */
    public function createComponentNewsForm()
    {
        $f = $this->formFactory->create();
        $f->addText('title', 'Titulek')->setRequired();
        $f->addSelect('year', 'Rok', $this->YR->getYearChoices())->setDefaultValue($this->YR->getSelectedYear()->id);
        $f->addCheckbox('texy', 'Užít Texy')->setDefaultValue(TRUE);
        $f->addCheckbox('published', 'Publikováno')->setDefaultValue(TRUE);

        $f->addHidden('id');
        $content = $f->addTextArea('content', 'Obsah')->setRequired();
        $content->getControlPrototype()->attrs['style'] = 'width: 100%; max-width: 100%;';
        $rows = 10;
        if ($this->news && $this->news->content instanceof Countable) {
            $rows = count(Strings::match($this->news->content, '#\n#')) + 5;
        }
        $content->getControlPrototype()->attrs['rows'] = $rows;
        $f->addSubmit('submit', $this->news ? 'Upravit' : 'Přidat');
        $f->onSuccess[] = [$this, 'newsFormSubmitted'];
        return $f;
    }

    /**
     * @param Form      $form
     * @param ArrayHash $values
     * @throws \LeanMapper\Exception\InvalidArgumentException
     */
    public function newsFormSubmitted(Form $form, ArrayHash $values)
    {
        if ($this->news) {
            $news = $this->news;
        } else {
            $news = new News();
            $news->added = new DateTime();
            $news->tag = NULL;
        }
        $news->year = $this->YR->get($values->year, FALSE);
        if (!$news->published && $values['published'])
            $news->added = new DateTime();
        $news->assign($values, ['title', 'content', 'texy', 'published']);
        $news->updated = new DateTime();

        try {
            $this->NR->persist($news);
        } catch (DriverException $e) {
            $this->presenter->flashMessage('Chyba při ukládání novinky!', 'warning');
            return;
        }
        $this->tagManager->getTag($news);
        $form->setValues([], TRUE);
        $this->presenter->flashMessage($values->id ? 'Novinka upravena!' : 'Novinka přidána!', 'success');
    }


    public function handleCreateTag()
    {
        $tag = $this->tagManager->getTag($this->news);
        $this->presenter->flashMessage("Tag k novince vytvořen tag {$tag->slug}.");
        $this->presenter->redrawControl('content');
    }
}