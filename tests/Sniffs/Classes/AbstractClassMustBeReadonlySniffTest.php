<?php

declare(strict_types=1);

namespace FundrikStandard\Tests\Sniffs\Classes;

use FundrikStandard\Sniffs\Classes\AbstractClassMustBeReadonlySniff;
use FundrikStandard\Tests\AbstractSniffTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass( AbstractClassMustBeReadonlySniff::class )]
final class AbstractClassMustBeReadonlySniffTest extends AbstractSniffTestCase {

	protected function get_sniff_class(): string {

		return AbstractClassMustBeReadonlySniff::class;
	}

	#[Test]
	public function detects_abstract_class_without_readonly(): void {

		$this->assert_sniff_error_code(
			7,
			'FundrikStandard.Classes.AbstractClassMustBeReadonly.AbstractClassNotReadonly',
		);
	}

	#[Test]
	public function does_not_report_abstract_readonly_class(): void {

		$this->assert_sniff_ok( 13 );
	}
}
