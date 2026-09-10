<?php

namespace MediaWiki\Extension\SiteMatrix;

/**
 * @covers \MediaWiki\Extension\SiteMatrix\SiteMatrixLookup
 */
class SiteMatrixLookupTest extends \MediaWikiIntegrationTestCase {

	public function testGetEmptySiteMatrix() {
		$siteMatrix = $this->getMockBuilder( SiteMatrix::class )
			->disableOriginalConstructor()
			->getMock();
		$siteMatrix->method( 'getLangList' )->willReturn( [] );
		$siteMatrix->method( 'getSpecials' )->willReturn( [] );
		$siteMatrix->method( 'getSites' )->willReturn( [] );

		$lookup = new SiteMatrixLookup(
			$siteMatrix,
			'wiki'
		);

		$this->assertCount( 0, $lookup->getSites() );
	}

	public function testGetPartialSiteMatrix() {
		$siteMatrix = $this->getMockBuilder( SiteMatrix::class )
			->disableOriginalConstructor()
			->getMock();
		$siteMatrix->method( 'getLangList' )->willReturn( [ 'de', 'en' ] );
		$siteMatrix->method( 'getSpecials' )->willReturn( [] );
		$siteMatrix->method( 'getSites' )->willReturn( [ 'wiki', 'wikibooks' ] );
		$siteMatrix->method( 'exist' )->willReturnCallback(
			static fn ( $lang, $group ) => !( $lang == 'en' && $group == 'wikibooks' )
		);
		$siteMatrix->method( 'getDBName' )->willReturnCallback(
			static fn ( $lang, $group ) => $lang . $group
		);
		$siteMatrix->method( 'getLanguageCode' )->willReturnArgument( 0 );
		$siteMatrix->method( 'getCanonicalUrl' )->willReturnCallback(
			static function ( $lang, $group ) {
				switch ( [ $lang, $group ] ) {
					case [ 'de', 'wiki' ]:
						return 'https://de.wikipedia.org';
					case [ 'en', 'wiki' ]:
						return 'https://en.wikipedia.org';
					case [ 'de', 'wikibooks' ]:
						return 'https://de.wikibooks.org';
				}
			}
		);

		$lookup = new SiteMatrixLookup(
			$siteMatrix,
			'wiki'
		);

		$sites = $lookup->getSites();
		$this->assertCount( 3, $sites );
		$this->assertEquals(
			[
				'globalid' => 'enwiki',
				'type' => 'mediawiki',
				'group' => 'wiki',
				'source' => 'local',
				'language' => 'en',
				'localids' => [
					'equivalent' => [ 'en' ],
					'interwiki' => [ 'en' ],
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://en.wikipedia.org/w/$1',
						'page_path' => 'https://en.wikipedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			],
			$sites->getSite( 'enwiki' )->__serialize()
		);
		$this->assertEquals(
			[
				'globalid' => 'dewiki',
				'type' => 'mediawiki',
				'group' => 'wiki',
				'source' => 'local',
				'language' => 'de',
				'localids' => [
					'equivalent' => [ 'de' ],
					'interwiki' => [ 'de' ],
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://de.wikipedia.org/w/$1',
						'page_path' => 'https://de.wikipedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			],
			$sites->getSite( 'dewiki' )->__serialize()
		);
		$this->assertEquals(
			[
				'globalid' => 'dewikibooks',
				'type' => 'mediawiki',
				'group' => 'wikibooks',
				'source' => 'local',
				'language' => 'de',
				'localids' => [],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://de.wikibooks.org/w/$1',
						'page_path' => 'https://de.wikibooks.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			],
			$sites->getSite( 'dewikibooks' )->__serialize()
		);
	}

	public function testGetSpecialSiteMatrix() {
		$siteMatrix = $this->getMockBuilder( SiteMatrix::class )
			->disableOriginalConstructor()
			->getMock();
		$siteMatrix->method( 'getLangList' )->willReturn( [] );
		$siteMatrix->method( 'getSpecials' )->willReturn( [
			[ 'meta', 'wiki' ],
			[ 'arbcom_nl', 'wiki' ],
			[ 'ae', 'wikimedia' ]
		] );
		$siteMatrix->method( 'getSites' )->willReturn( [ 'wiki', 'wikibooks' ] );
		$siteMatrix->method( 'getDBName' )->willReturnCallback(
			static fn ( $lang, $group ) => $lang . $group
		);
		$siteMatrix->method( 'getLanguageCode' )->willReturnCallback(
			static function ( $lang, $group ) {
				switch ( [ $lang, $group ] ) {
					case [ 'meta', 'wiki' ]:
						return 'meta';
					case [ 'arbcom_nl', 'wiki' ]:
						return 'nl';
					case [ 'ae', 'wikimedia' ]:
						return 'en';
				}
			}
		);
		$siteMatrix->method( 'getCanonicalUrl' )->willReturnCallback(
			static function ( $lang, $group ) {
				switch ( [ $lang, $group ] ) {
					case [ 'meta', 'wiki' ]:
						return 'https://meta.wikimedia.org';
					case [ 'arbcom_nl', 'wiki' ]:
						return 'https://arbcom-nl.wikipedia.org';
					case [ 'ae', 'wikimedia' ]:
						return 'https://ae.wikimedia.org';
				}
			}
		);

		$lookup = new SiteMatrixLookup(
			$siteMatrix,
			'wiki'
		);

		$sites = $lookup->getSites();
		$this->assertEquals(
			[
				'globalid' => 'metawiki',
				'type' => 'mediawiki',
				'group' => 'wiki',
				'source' => 'local',
				'language' => 'meta',
				'localids' => [
					'interwiki' => [ 'meta' ],
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://meta.wikimedia.org/w/$1',
						'page_path' => 'https://meta.wikimedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			], $sites->getSite( 'metawiki' )->__serialize()
		);

		$this->assertEquals(
			[
				'globalid' => 'arbcom_nlwiki',
				'type' => 'mediawiki',
				'group' => 'wiki',
				'source' => 'local',
				'language' => 'nl',
				'localids' => [
					'interwiki' => [ 'arbcom-nl' ],
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://arbcom-nl.wikipedia.org/w/$1',
						'page_path' => 'https://arbcom-nl.wikipedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			], $sites->getSite( 'arbcom_nlwiki' )->__serialize()
		);

		$this->assertEquals(
			[
				'globalid' => 'aewikimedia',
				'type' => 'mediawiki',
				'group' => 'wikimedia',
				'source' => 'local',
				'language' => 'en',
				'localids' => [
					'interwiki' => [ 'aewikimedia' ]
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://ae.wikimedia.org/w/$1',
						'page_path' => 'https://ae.wikimedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			], $sites->getSite( 'aewikimedia' )->__serialize()
		);
	}

	public function testLanguageMapping() {
		$siteMatrix = $this->getMockBuilder( SiteMatrix::class )
			->disableOriginalConstructor()
			->getMock();
		$siteMatrix->method( 'getLangList' )->willReturn( [ 'be-tarask', 'be-x-old' ] );
		$siteMatrix->method( 'getSites' )->willReturn( [ 'wiki' ] );
		$siteMatrix->method( 'getSpecials' )->willReturn( [] );
		$siteMatrix->method( 'exist' )->willReturnCallback(
			static fn ( $lang, $group ) => $lang == 'be-x-old' && $group == 'wiki'
		);
		$siteMatrix->method( 'getDBName' )->willReturnCallback(
			static fn ( $lang, $group ) => str_replace( '-', '_', $lang ) . $group
		);
		$siteMatrix->method( 'getLanguageCode' )
			->with( 'be-x-old', 'wiki' )
			->willReturn( 'be-tarask' );
		$siteMatrix->method( 'getCanonicalUrl' )->willReturn( 'https://be-tarask.wikipedia.org' );

		$lookup = new SiteMatrixLookup(
			$siteMatrix,
			'wiki'
		);

		$sites = $lookup->getSites();
		$this->assertCount( 1, $sites );
		$this->assertEquals(
			[
				'globalid' => 'be_x_oldwiki',
				'type' => 'mediawiki',
				'group' => 'wiki',
				'source' => 'local',
				'language' => 'be-tarask',
				'localids' => [
					'equivalent' => [ 'be-tarask' ],
					'interwiki' => [ 'be-tarask' ],
				],
				'config' => [],
				'data' => [
					'paths' => [
						'file_path' => 'https://be-tarask.wikipedia.org/w/$1',
						'page_path' => 'https://be-tarask.wikipedia.org/wiki/$1',
					]
				],
				'forward' => false,
				'internalid' => null,
			], $sites->getSite( 'be_x_oldwiki' )->__serialize()
		);
	}
}
