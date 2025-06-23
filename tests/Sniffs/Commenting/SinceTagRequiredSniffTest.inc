<?php


class MissingSinceClass {
}

// Invalid: has docblock but no @since
/**
 * Some comment.
 */
function missing_since_function() {
}

// Invalid: method with no @since
class AnotherClass {
	/** Just a note */
	public function missing_since_method() {}
}

// Valid: class with @since
/**
 * @since 1.0.0
 */
class ValidClass {
}

/**
 * @since 1.0.0
 */
function valid_function() {}

/**
 * @since 1.0.0
 */
class WithValidMethod {
	/**
	 * @since 1.0.0
	 */
	public function valid_method() {}
}
