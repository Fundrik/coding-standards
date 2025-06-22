<?php

declare(strict_types=1);

namespace FundrikStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
/**
 * Reports an error when a class is declared `final` but not `readonly`,
 * unless it extends a class from an excluded list (e.g., Throwable or PHPUnit constraints)
 * or the class itself is in an excluded list.
 *
 * @property array<string> $excludedParentClasses List of fully qualified class names to exclude from readonly check.
 * @property array<string> $excludedClasses List of fully qualified class names of classes excluded from readonly check.
 *
 * @since 1.0.0
 */
final class FinalClassMustBeReadonlySniff implements Sniff {

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	/**
	 * List of base classes that are excluded from readonly requirement.
	 *
	 * This should be set via ruleset.xml as a <property>.
	 *
	 * @var array<int, string>
	 */
	public array $excludedParentClasses = [];

	/**
	 * List of specific classes that are excluded from readonly requirement.
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
	 * If the class is declared as `final`, but not `readonly`, an error is reported.
	 * Skips classes that extend any from `$excludedParentClasses` or
	 * are themselves in `$excludedClasses`.
	 *
	 * @since 1.0.0
	 *
	 * @param File $phpcs_file The file being scanned.
	 * @param int $stack_ptr The position of the current token in the stack.
	 */
	public function process( File $phpcs_file, $stack_ptr ): void {

		$tokens = $phpcs_file->getTokens();

		// Get class name token and resolved FQCN of the current class.
		$class_name_token = $phpcs_file->findNext( T_STRING, $stack_ptr );

		if ( $class_name_token === false ) {
			return;
		}

		$full_class_name = $this->resolve_fully_qualified_name( $phpcs_file, $class_name_token );

		$normalized_class_name = ltrim( $full_class_name, '\\' );

		$normalized_excluded_classes = array_map(
			static fn ( $c ) => ltrim( $c, '\\' ),
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$this->excludedClasses,
		);

		// Skip if class is in excludedClasses.
		if ( in_array( $normalized_class_name, $normalized_excluded_classes, true ) ) {
			return;
		}

		// Check for parent class.
		// phpcs:ignore SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall
		$extends_ptr = $phpcs_file->findNext(
			T_EXTENDS,
			$stack_ptr,
			$tokens[ $stack_ptr ]['scope_opener'] ?? null,
		);

		if ( $extends_ptr !== false ) {
			$parent_class_token = $phpcs_file->findNext( T_STRING, $extends_ptr + 1 );
			$parent_class_name = $parent_class_token !== false
				? $tokens[ $parent_class_token ]['content']
				: null;

			if ( $parent_class_name !== null ) {
				$full_parent_name = $this->resolve_fully_qualified_name( $phpcs_file, $parent_class_token );

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				foreach ( $this->excludedParentClasses as $excluded_class ) {

					if (
						$full_parent_name === ltrim( $excluded_class, '\\' )
						|| is_subclass_of( $full_parent_name, ltrim( $excluded_class, '\\' ) )
					) {
						return;
					}
				}
			}
		}

		$is_final = false;
		$is_readonly = false;

		// Walk backward to find modifiers.
		for ( $i = $stack_ptr - 1; $i >= 0; --$i ) {
			$code = $tokens[ $i ]['code'];

			if ( in_array( $code, [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				continue;
			}

			if ( $code === T_FINAL ) {
				$is_final = true;
			} elseif ( $code === T_READONLY ) {
				$is_readonly = true;
			} else {
				break;
			}
		}

		if ( ! $is_final || $is_readonly ) {
			return;
		}

		// phpcs:ignore SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall
		$phpcs_file->addError(
			'Final class must also be declared readonly.',
			$stack_ptr,
			'FinalClassNotReadonly',
		);
	}

	/**
	 * Resolves the fully qualified name of a class based on use statements and current namespace.
	 *
	 * @since 1.0.0
	 *
	 * @param File $phpcs_file The current file being scanned.
	 * @param int $ptr Pointer to the T_STRING token of the class name.
	 *
	 * @return string Fully qualified class name.
	 */
	private function resolve_fully_qualified_name( File $phpcs_file, int $ptr ): string {

		$tokens = $phpcs_file->getTokens();
		$class_name = $tokens[ $ptr ]['content'];

		$use_statements = $this->get_use_statements( $phpcs_file );

		if ( isset( $use_statements[ $class_name ] ) ) {
			return $use_statements[ $class_name ];
		}

		$namespace = $this->get_current_namespace( $phpcs_file );

		if ( $namespace !== '' ) {
			return $namespace . '\\' . $class_name;
		}

		return $class_name;
	}

	/**
	 * Parses the file and returns a map of imported class names (via `use`) to their FQCNs.
	 *
	 * @since 1.0.0
	 *
	 * @param File $phpcs_file The current file being scanned.
	 *
	 * @return array<string, string> Map of alias => fully qualified class name.
	 */
	private function get_use_statements( File $phpcs_file ): array {

		$tokens = $phpcs_file->getTokens();
		$uses = [];
		$token_count = count( $tokens );

		for ( $i = 0; $i < $token_count; $i++ ) {

			if ( $tokens[ $i ]['code'] !== T_USE ) {
				continue;
			}

			$end = $phpcs_file->findEndOfStatement( $i );
			$fqcn = '';
			$alias = '';
			$as_found = false;

			for ( $j = $i + 1; $j < $end; $j++ ) {

				if ( $tokens[ $j ]['code'] === T_STRING || $tokens[ $j ]['code'] === T_NS_SEPARATOR ) {

					if ( ! $as_found ) {
						$fqcn .= $tokens[ $j ]['content'];
					} else {
						$alias .= $tokens[ $j ]['content'];
					}
				}

				if ( $tokens[ $j ]['code'] !== T_AS ) {
					continue;
				}

				$as_found = true;
			}

			if ( $fqcn === '' ) {
				continue;
			}

			$key = $alias !== '' ? $alias : basename( str_replace( '\\', '/', $fqcn ) );
			$uses[ $key ] = $fqcn;
		}

		return $uses;
	}

	/**
	 * Returns the namespace of the currently scanned file.
	 *
	 * @since 1.0.0
	 *
	 * @param File $phpcs_file The current file being scanned.
	 *
	 * @return string Fully qualified namespace (without leading backslash).
	 */
	private function get_current_namespace( File $phpcs_file ): string {

		$tokens = $phpcs_file->getTokens();
		$namespace = '';

		foreach ( $tokens as $ptr => $token ) {

			if ( $token['code'] === T_NAMESPACE ) {
				$namespace_parts = [];
				$next = $phpcs_file->findNext( [ T_STRING, T_NS_SEPARATOR ], $ptr + 1, null, false, null, true );

				while ( $next !== false && in_array( $tokens[ $next ]['code'], [ T_STRING, T_NS_SEPARATOR ], true ) ) {
					$namespace_parts[] = $tokens[ $next ]['content'];
					++$next;
				}

				$namespace = implode( '', $namespace_parts );
				break;
			}
		}

		return $namespace;
	}
}
// phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
