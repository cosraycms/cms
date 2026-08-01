<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Definitions;
use Cosray\Tests\Fixtures\FieldDefinition\ClassLabelFieldsetNode;
use Cosray\Tests\Fixtures\FieldDefinition\DuplicateFieldNode;
use Cosray\Tests\Fixtures\FieldDefinition\EmptyEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\FieldsetFieldNode;
use Cosray\Tests\Fixtures\FieldDefinition\InitializedEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\InlineLabelNode;
use Cosray\Tests\Fixtures\FieldDefinition\InvalidWidthNode;
use Cosray\Tests\Fixtures\FieldDefinition\LabellessFieldsetNode;
use Cosray\Tests\Fixtures\FieldDefinition\NameCollisionNode;
use Cosray\Tests\Fixtures\FieldDefinition\NestedEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\NullableEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\PromotedEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\ReadonlyEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\StaticEmbedNode;
use Cosray\Tests\Fixtures\FieldDefinition\UnionEmbedNode;
use Cosray\Tests\Fixtures\Node\PlainPage;
use Cosray\Tests\Fixtures\Node\TestBaseFields;
use Cosray\Tests\Fixtures\Node\TestEmbeddedDocument;
use Cosray\Tests\Fixtures\Node\TestInlineEmbeddedDocument;
use Cosray\Tests\TestCase;

final class FieldDefinitionsTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		Definitions::clear();
	}

	public function testDirectFieldDefinitionsRemainUnchanged(): void
	{
		$definitions = Definitions::for(PlainPage::class);

		$this->assertSame(['heading', 'body'], $definitions->names());
		$this->assertSame([], $definitions->embedded());
		$this->assertSame([], $definitions->fieldsets());
		$this->assertSame('heading', $definitions->field('heading')?->name);
		$this->assertNull($definitions->field('missing'));
	}

	public function testInlineEmbedSplicesFieldsAtItsPropertyPosition(): void
	{
		$definitions = Definitions::for(TestInlineEmbeddedDocument::class);

		$this->assertSame(['before', 'title', 'body', 'after'], $definitions->names());
		$this->assertSame([], $definitions->fieldsets());
		$this->assertSame('baseFields', $definitions->field('title')?->embedded);
		$this->assertSame(TestBaseFields::class, $definitions->embed('baseFields')?->type);
		$this->assertNull($definitions->embed('missing'));
		$this->assertSame($definitions, Definitions::for(TestInlineEmbeddedDocument::class));
	}

	public function testFieldsetRecordsMembersAndPropertyMetadata(): void
	{
		$definitions = Definitions::for(TestEmbeddedDocument::class);
		$fieldset = $definitions->fieldsets()[0];

		$this->assertSame(['before', 'title', 'body', 'after'], $definitions->names());
		$this->assertSame('baseFields', $fieldset->name);
		$this->assertSame('Document fields', $fieldset->label);
		$this->assertSame('Reusable document fields', $fieldset->description);
		$this->assertSame(50, $fieldset->width);
		$this->assertSame(['title', 'body'], $fieldset->fields);
	}

	public function testFieldsetUsesEmbeddedClassLabelByDefault(): void
	{
		$fieldset = Definitions::for(ClassLabelFieldsetNode::class)->fieldsets()[0];

		$this->assertSame('Base fields', $fieldset->label);
		$this->assertNull($fieldset->description);
		$this->assertSame(100, $fieldset->width);
	}

	public function testFieldsetMayBeLabelLess(): void
	{
		$fieldset = Definitions::for(LabellessFieldsetNode::class)->fieldsets()[0];

		$this->assertNull($fieldset->label);
	}

	public function testDuplicateFlatFieldsAreRejected(): void
	{
		$this->throws(RuntimeException::class, "Field 'title' is declared more than once");

		Definitions::for(DuplicateFieldNode::class);
	}

	public function testFieldAndEmbedNameCollisionIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'collides with the flat field');

		Definitions::for(NameCollisionNode::class);
	}

	public function testNestedEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'contains unsupported nested embed');

		Definitions::for(NestedEmbedNode::class);
	}

	public function testNullableEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'requires one non-nullable named type');

		Definitions::for(NullableEmbedNode::class);
	}

	public function testUnionEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'requires one non-nullable named type');

		Definitions::for(UnionEmbedNode::class);
	}

	public function testStaticEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'must not be static');

		Definitions::for(StaticEmbedNode::class);
	}

	public function testReadonlyEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'must not be readonly');

		Definitions::for(ReadonlyEmbedNode::class);
	}

	public function testInitializedEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'must be uninitialized');

		Definitions::for(InitializedEmbedNode::class);
	}

	public function testPromotedEmbedIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'must be uninitialized');

		Definitions::for(PromotedEmbedNode::class);
	}

	public function testEmptyEmbeddedTypeIsRejected(): void
	{
		$this->throws(RuntimeException::class, 'does not declare any fields');

		Definitions::for(EmptyEmbedNode::class);
	}

	public function testFieldsetRequiresEmbeddedProperty(): void
	{
		$this->throws(RuntimeException::class, 'requires an Embedded-typed property');

		Definitions::for(FieldsetFieldNode::class);
	}

	public function testInlineEmbedRejectsLayoutAttributes(): void
	{
		$this->throws(RuntimeException::class, 'requires #[Fieldset]');

		Definitions::for(InlineLabelNode::class);
	}

	public function testFieldsetWidthMustBeValid(): void
	{
		$this->throws(RuntimeException::class, 'must be between 1 and 100');

		Definitions::for(InvalidWidthNode::class);
	}
}
