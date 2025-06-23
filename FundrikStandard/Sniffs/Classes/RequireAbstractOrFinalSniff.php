<?php

declare(strict_types=1);

namespace FundrikStandard\Sniffs\Classes;

use FundrikStandard\FullyQualifiedNameTrait;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Sniffs\Classes\RequireAbstractOrFinalSniff as BaseSniff;

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
/**
 * Reports an error when a class is declared `final` but not `readonly`,
 * unless it extends a class from an excluded list (e.g., Throwable or PHPUnit constraints)
 * or the class itself is in an excluded list.
 *
 * @since 1.0.0
 *
 * @todo Add test for excludedClasses argument.
 */
final class RequireAbstractOrFinalSniff extends BaseSniff implements Sniff {

	use FullyQualifiedNameTrait;

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	/**
	 * List of specific classes that are excluded from abstract/final requirement.
	 *
	 * This should be set via ruleset.xml as a <property>.
	 *
	 * @var array<int, string>
	 */
	public array $excludedClasses = [];
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	/**
	 * Returns the token types this sniff is interested in.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int>
	 */
	public function register(): array {

		return [ T_CLASS ];
	}

	/**
	 * Processes each class declaration.
	 *
	 * Skips classes that are in the exclusion list.
	 *
	 * @since 1.0.0
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int $stack_ptr The position of the current token in the stack.
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {

		$class_name_token = $phpcs_file->findNext( T_STRING, $stack_ptr );

		if ( $class_name_token === false ) {
			return;
		}

		$full_class_name = $this->resolve_fully_qualified_name( $phpcs_file, $class_name_token );
		$normalized_class_name = ltrim( $full_class_name, '\\' );

		$normalized_excluded_classes = array_map(
			static fn ( $fqcn ) => ltrim( $fqcn, '\\' ),
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->excludedClasses,
		);

		if ( in_array( $normalized_class_name, $normalized_excluded_classes, true ) ) {
			return;
		}

		// Delegate to parent sniff logic.
		parent::process( $phpcs_file, $stack_ptr );
	}
}
// phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
