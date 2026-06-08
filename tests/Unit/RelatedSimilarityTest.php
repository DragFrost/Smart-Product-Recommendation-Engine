<?php
/**
 * SPRE Related Similarity Unit Tests.
 *
 * @package SPRE\Tests\Unit
 */

declare(strict_types=1);

namespace SPRE\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SPRE\Recommendation\Algorithms\RelatedSimilarity;
use SPRE\Database\RelationsRepository;
use ReflectionMethod;
use WC_Product;

/**
 * Class RelatedSimilarityTest
 *
 * Validates the scoring weights.
 */
class RelatedSimilarityTest extends TestCase {

	/**
	 * Test that the similarity score math aggregates correctly.
	 */
	public function test_similarity_score_calculation(): void {
		// Mock relations repository
		$repo_mock = $this->createMock( RelationsRepository::class );
		$algo      = new RelatedSimilarity( $repo_mock );

		// Use reflection to expose private calculate_similarity_score method
		$method = new ReflectionMethod( RelatedSimilarity::class, 'calculate_similarity_score' );
		$method->setAccessible( true );

		// Mock WooCommerce Product
		$product_mock = $this->createMock( WC_Product::class );
		$product_mock->method( 'get_category_ids' )->willReturn( [ 12, 15 ] );
		$product_mock->method( 'get_tag_ids' )->willReturn( [ 5, 8 ] );
		$product_mock->method( 'get_price' )->willReturn( '89.99' );
		$product_mock->method( 'get_attribute' )->with( 'pa_brand' )->willReturn( 'BrandX' );
		$product_mock->method( 'get_meta' )->with( 'total_sales' )->willReturn( 16 ); // log(16, 2) = 4 pts

		// Scenario A: Exact match (Should score high: 30 + 30 + 20 + 20 + 4 = 104)
		$score_a = $method->invoke(
			$algo,
			[ 12, 15 ],
			[ 5, 8 ],
			89.99,
			'BrandX',
			$product_mock
		);

		$this->assertEquals( 104.0, $score_a );

		// Scenario B: Partial Match (Price difference and no brand overlap)
		// Cats match (30), tag half matches (15), price deviating, no brand.
		$product_mock_b = $this->createMock( WC_Product::class );
		$product_mock_b->method( 'get_category_ids' )->willReturn( [ 12 ] );
		$product_mock_b->method( 'get_tag_ids' )->willReturn( [ 5 ] ); // Only 1 of 2 matches
		$product_mock_b->method( 'get_price' )->willReturn( '109.99' ); // Deviation
		$product_mock_b->method( 'get_attribute' )->with( 'pa_brand' )->willReturn( 'BrandY' );
		$product_mock_b->method( 'get_meta' )->with( 'total_sales' )->willReturn( 0 );

		$score_b = $method->invoke(
			$algo,
			[ 12, 15 ],
			[ 5, 8 ],
			89.99,
			'BrandX',
			$product_mock_b
		);

		$this->assertGreaterThan( 45.0, $score_b ); // 30 (cats) + 15 (tags) = 45 base
		$this->assertLessThan( 75.0, $score_b );    // Should be less than exact match
	}
}
