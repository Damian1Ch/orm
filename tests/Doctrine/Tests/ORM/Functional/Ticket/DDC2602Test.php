<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Annotation as ORM;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @group performance
 * @group DDC-2602
 */
class DDC2602Test extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->schemaTool->createSchema(
            [
                $this->em->getClassMetadata(DDC2602User::class),
                $this->em->getClassMetadata(DDC2602Biography::class),
                $this->em->getClassMetadata(DDC2602BiographyField::class),
                $this->em->getClassMetadata(DDC2602BiographyFieldChoice::class),
            ]
        );

        $this->loadFixture();
    }

    protected function tearDown() : void
    {
        parent::tearDown();

        $this->schemaTool->dropSchema(
            [
                $this->em->getClassMetadata(DDC2602User::class),
                $this->em->getClassMetadata(DDC2602Biography::class),
                $this->em->getClassMetadata(DDC2602BiographyField::class),
                $this->em->getClassMetadata(DDC2602BiographyFieldChoice::class),
            ]
        );
    }

    public function testPostLoadListenerShouldBeAbleToRunQueries() : void
    {
        $eventManager = $this->em->getEventManager();
        $eventManager->addEventListener([Events::postLoad], new DDC2602PostLoadListener());

        $result = $this->em
            ->createQuery('SELECT u, b FROM Doctrine\Tests\ORM\Performance\DDC2602User u JOIN u.biography b')
            ->getResult();

        self::assertCount(2, $result);
        self::assertCount(2, $result[0]->biography->fieldList);
        self::assertCount(1, $result[1]->biography->fieldList);
    }

    private function loadFixture() : void
    {
        $user1                 = new DDC2602User();
        $user2                 = new DDC2602User();
        $biography1            = new DDC2602Biography();
        $biography2            = new DDC2602Biography();
        $biographyField1       = new DDC2602BiographyField();
        $biographyField2       = new DDC2602BiographyField();
        $biographyFieldChoice1 = new DDC2602BiographyFieldChoice();
        $biographyFieldChoice2 = new DDC2602BiographyFieldChoice();
        $biographyFieldChoice3 = new DDC2602BiographyFieldChoice();
        $biographyFieldChoice4 = new DDC2602BiographyFieldChoice();
        $biographyFieldChoice5 = new DDC2602BiographyFieldChoice();
        $biographyFieldChoice6 = new DDC2602BiographyFieldChoice();

        $user1->name = 'Gblanco';
        $user1->biography = $biography1;

        $user2->name = 'Beberlei';
        $user2->biography = $biography2;

        $biography1->user = $user1;
        $biography1->content = '[{"field": 1, "choiceList": [1,3]}, {"field": 2, "choiceList": [5]}]';

        $biography2->user = $user2;
        $biography2->content = '[{"field": 1, "choiceList": [1,2,3,4]}]';

        $biographyField1->alias = 'question_1';
        $biographyField1->label = 'Question 1';
        $biographyField1->choiceList->add($biographyFieldChoice1);
        $biographyField1->choiceList->add($biographyFieldChoice2);
        $biographyField1->choiceList->add($biographyFieldChoice3);
        $biographyField1->choiceList->add($biographyFieldChoice4);

        $biographyField2->alias = 'question_2';
        $biographyField2->label = 'Question 2';
        $biographyField2->choiceList->add($biographyFieldChoice5);
        $biographyField2->choiceList->add($biographyFieldChoice6);

        $biographyFieldChoice1->field = $biographyField1;
        $biographyFieldChoice1->label = 'Answer 1.1';

        $biographyFieldChoice2->field = $biographyField1;
        $biographyFieldChoice2->label = 'Answer 1.2';

        $biographyFieldChoice3->field = $biographyField1;
        $biographyFieldChoice3->label = 'Answer 1.3';

        $biographyFieldChoice4->field = $biographyField1;
        $biographyFieldChoice4->label = 'Answer 1.4';

        $biographyFieldChoice5->field = $biographyField2;
        $biographyFieldChoice5->label = 'Answer 2.1';

        $biographyFieldChoice6->field = $biographyField2;
        $biographyFieldChoice6->label = 'Answer 2.2';

        $this->em->persist($user1);
        $this->em->persist($user2);

        $this->em->persist($biographyField1);
        $this->em->persist($biographyField2);

        $this->em->flush();
        $this->em->clear();
    }
}


class DDC2602PostLoadListener
{
    public function postLoad(LifecycleEventArgs $event) : void
    {
        $entity = $event->getEntity();

        if ( ! ($entity instanceof DDC2602Biography)) {
            return;
        }

        $entityManager = $event->getEntityManager();
        $query         = $entityManager->createQuery('
            SELECT f, fc
              FROM Doctrine\Tests\ORM\Functional\Ticket\DDC2602BiographyField f INDEX BY f.id
              JOIN f.choiceList fc INDEX BY fc.id
        ');

        $result    = $query->getResult();
        $content   = json_decode($entity->content);
        $fieldList = new ArrayCollection();

        foreach ($content as $selection) {
            $field      = $result[$selection->field];
            $choiceList = $selection->choiceList;
            $fieldSelection     = new DDC2602FieldSelection();

            $fieldSelection->field      = $field;
            $fieldSelection->choiceList = $field->choiceList->filter(function ($choice) use ($choiceList) {
                return in_array($choice->id, $choiceList);
            });

            $fieldList->add($fieldSelection);
        }

        $entity->fieldList = $fieldList;
    }
}


/**
 * @ORM\Entity
 */
class DDC2602User
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    public $id;

    /**
     * @ORM\Column(type="string", length=15)
     *
     * @var string
     */
    public $name;

    /**
     * @ORM\OneToOne(
     *     targetEntity=DDC2602Biography::class,
     *     inversedBy="user",
     *     cascade={"persist", "refresh", "remove"}
     * )
     * @ORM\JoinColumn(nullable=false)
     *
     * @var DDC2602Biography
     */
    public $biography;
}

/**
 * @ORM\Entity
 */
class DDC2602Biography
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    public $id;

    /**
     * @ORM\OneToOne(
     *     targetEntity=DDC2602User::class,
     *     mappedBy="biography",
     *     cascade={"persist", "refresh"}
     * )
     *
     * @var DDC2602User
     */
    public $user;

    /**
     * @ORM\Column(type="text", nullable=true)
     *
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    public $fieldList = [];
}

/**
 * @ORM\Entity
 */
class DDC2602BiographyField
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    public $id;

    /**
     * @ORM\Column(type="string", unique=true, length=100)
     */
    public $alias;

    /**
     * @ORM\Column(type="string", length=100)
     */
    public $label;

    /**
     * @ORM\OneToMany(
     *     targetEntity=DDC2602BiographyFieldChoice::class,
     *     mappedBy="field",
     *     cascade={"persist", "refresh"}
     * )
     *
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    public $choiceList;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->choiceList = new ArrayCollection();
    }
}

/**
 * @ORM\Entity
 */
class DDC2602BiographyFieldChoice
{
    /**
     * @ORM\Id @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var integer
     */
    public $id;

    /**
     * @ORM\Column(type="string", unique=true, length=100)
     */
    public $label;

    /**
     * @ORM\ManyToOne(
     *     targetEntity=DDC2602BiographyField::class,
     *     inversedBy="choiceList"
     * )
     * @ORM\JoinColumn(onDelete="CASCADE")
     *
     * @var DDC2602BiographyField
     */
    public $field;
}

class DDC2602FieldSelection
{
    /**
     * @var DDC2602BiographyField
     */
    public $field;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    public $choiceList;

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->choiceList = new ArrayCollection();
    }
}
