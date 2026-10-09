<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Tests\Controller\Management;

use c975L\BookBundle\Controller\Management\Trait\SocialStatusCrudTrait;
use c975L\BookBundle\Entity\Book;
use c975L\UiBundle\Contract\SocialContentStatusProviderInterface;
use c975L\UiBundle\Model\SocialContentStatus;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

// The social post badge of the books and planches lists: SocialBundle asked about the rows of the page shown, in one call
class SocialStatusCrudTraitTest extends TestCase
{
    // Only the rows of the page are asked about, and each gets the badge of its own post
    public function testTheRowsOfThePageAreAskedAboutInOneCall(): void
    {
        $statuses = $this->createMock(SocialContentStatusProviderInterface::class);
        $statuses->expects($this->once())->method('getStatuses')->with('book', ['1', '2'])->willReturn([
            '2' => new SocialContentStatus(SocialContentStatus::PUBLISHED, new \DateTimeImmutable('2026-10-09')),
        ]);
        [$first, $second] = [$this->row(1), $this->row(2)];

        $this->controller($statuses, [$first, $second])->index($this->context());

        $this->assertSame('', $this->badge($first));
        $this->assertSame('<span class="badge badge-success">label.book_social_published</span>', $this->badge($second));
    }

    // A post still in draft holds the row all the same, shown apart from a published one
    public function testADraftIsShownAsAWarning(): void
    {
        $statuses = $this->createStub(SocialContentStatusProviderInterface::class);
        $statuses->method('getStatuses')->willReturn(['1' => new SocialContentStatus(SocialContentStatus::RESERVED, new \DateTimeImmutable())]);
        $row = $this->row(1);

        $this->controller($statuses, [$row])->index($this->context());

        $this->assertStringContainsString('badge-warning', $this->badge($row));
    }

    // An empty page asks nothing
    public function testAnEmptyPageAsksNothing(): void
    {
        $statuses = $this->createMock(SocialContentStatusProviderInterface::class);
        $statuses->expects($this->never())->method('getStatuses');

        $this->controller($statuses, [])->index($this->context());
    }

    // A site without SocialBundle has no column at all
    public function testNoColumnWithoutSocialBundle(): void
    {
        $this->assertSame([], $this->controller(null, [])->fields());
    }

    // A controller using the trait over a parent answering the given rows, as EasyAdmin's index() hands them over
    private function controller(?SocialContentStatusProviderInterface $socialStatuses, array $rows): SocialStatusCrudTraitTestController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SocialStatusCrudTraitTestController($socialStatuses, $translator, KeyValueStore::new(['entities' => new EntityCollection($rows)]));
    }

    // A listed book carrying the badge column, as EasyAdmin has processed it
    private function row(int $id): EntityDto
    {
        $book = new Book();
        new \ReflectionProperty(Book::class, 'id')->setValue($book, $id);
        $metadata = new ClassMetadata(Book::class);
        $metadata->setIdentifier(['id']);
        $row = new EntityDto(Book::class, $metadata, null, $book);
        $row->setFields(new FieldCollection([TextField::new('id')->setCustomOption('socialStatus', true)]));

        return $row;
    }

    private function badge(EntityDto $row): mixed
    {
        return $row->getFields()?->first()?->getFormattedValue();
    }

    // Never read by the parent below
    private function context(): AdminContext
    {
        return new \ReflectionClass(AdminContext::class)->newInstanceWithoutConstructor();
    }
}

// Stands for EasyAdmin's AbstractCrudController, whose index() answers the rows of the page
class SocialStatusCrudTraitTestParent
{
    public function __construct(private readonly KeyValueStore $responseParameters)
    {
    }

    public function index(AdminContext $context): KeyValueStore | Response
    {
        return $this->responseParameters;
    }
}

// A controller using the trait, as BookCrudController does
class SocialStatusCrudTraitTestController extends SocialStatusCrudTraitTestParent
{
    use SocialStatusCrudTrait;

    public function __construct(
        private readonly ?SocialContentStatusProviderInterface $socialStatuses,
        private readonly TranslatorInterface $translator,
        KeyValueStore $responseParameters,
    ) {
        parent::__construct($responseParameters);
    }

    public function fields(): array
    {
        return $this->socialStatusFields();
    }

    private function socialSourceType(): string
    {
        return 'book';
    }
}
