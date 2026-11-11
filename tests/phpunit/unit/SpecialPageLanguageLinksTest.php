<?php

namespace MediaWiki\Extension\SiteMatrix;

use MediaWiki\Interwiki\InterwikiLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\SiteMatrix\SpecialPageLanguageLinks
 */
class SpecialPageLanguageLinksTest extends MediaWikiUnitTestCase {

	private const WIKIS = [
		'de|wiki' => 'dewiki',
		'en|wiki' => 'enwiki',
		'fi|wiki' => 'fiwiki',
		'gsw|wiki' => 'alswiki',
		'no|wiki' => 'nowiki',
		'yue|wiki' => 'zh_yuewiki',
		'en|wiktionary' => 'enwiktionary',
		'fi|wiktionary' => 'fiwiktionary',
	];

	/**
	 * @param string[] $dbNames
	 * @param string[] $closed
	 * @param string[] $private
	 */
	private function newSiteMatrix(
		array $dbNames,
		array $closed = [],
		array $private = []
	): SiteMatrix {
		// Matrix keys are normalized language codes, e.g. alswiki is listed under gsw
		$matrix = [];
		foreach ( $dbNames as $lang => $dbName ) {
			[ $lang, $family ] = explode( '|', $lang );
			$matrix[$family][$lang] = $dbName;
		}

		$siteMatrix = $this->createMock( SiteMatrix::class );
		$siteMatrix->method( 'getSites' )->willReturn( [ 'wiki', 'wiktionary' ] );
		$siteMatrix->method( 'getLangList' )->willReturn( [ 'de', 'en', 'fi', 'gsw', 'no', 'yue' ] );
		$siteMatrix->method( 'exist' )->willReturnCallback(
			static fn ( $lang, $family ) => isset( $matrix[$family][$lang] )
		);
		$siteMatrix->method( 'getDBName' )->willReturnCallback(
			static fn ( $lang, $family ) => $matrix[$family][$lang] ?? $lang . $family
		);
		$siteMatrix->method( 'isClosed' )->willReturnCallback(
			static fn ( $lang, $family ) => in_array( $matrix[$family][$lang] ?? null, $closed, true )
		);
		$siteMatrix->method( 'isPrivate' )->willReturnCallback(
			static fn ( $dbName ) => in_array( $dbName, $private, true )
		);
		return $siteMatrix;
	}

	private function newInterwikiLookup( array $invalid = [] ): InterwikiLookup {
		$lookup = $this->createMock( InterwikiLookup::class );
		$lookup->method( 'isValidInterwiki' )->willReturnCallback(
			static fn ( $prefix ) => !in_array( $prefix, $invalid, true )
		);
		return $lookup;
	}

	public function testLinksToSameFamilyOnly() {
		$links = new SpecialPageLanguageLinks(
			$this->newSiteMatrix( self::WIKIS ),
			$this->newInterwikiLookup(),
			'enwiki'
		);

		$this->assertSame(
			[
				'de:Special:Watchlist',
				'fi:Special:Watchlist',
				'gsw:Special:Watchlist',
				'no:Special:Watchlist',
				'yue:Special:Watchlist',
			],
			$links->getLinks( 'Watchlist' )
		);
	}

	public function testOtherFamily() {
		$links = new SpecialPageLanguageLinks(
			$this->newSiteMatrix( self::WIKIS ),
			$this->newInterwikiLookup(),
			'fiwiktionary'
		);

		$this->assertSame( [ 'en:Special:RecentChanges' ], $links->getLinks( 'RecentChanges' ) );
	}

	public function testSkipsClosedAndPrivateWikis() {
		$links = new SpecialPageLanguageLinks(
			$this->newSiteMatrix( self::WIKIS, [ 'dewiki' ], [ 'fiwiki' ] ),
			$this->newInterwikiLookup(),
			'enwiki'
		);

		$this->assertSame(
			[ 'gsw:Special:Watchlist', 'no:Special:Watchlist', 'yue:Special:Watchlist' ],
			$links->getLinks( 'Watchlist' )
		);
	}

	public function testSkipsUnknownInterwikiPrefix() {
		$links = new SpecialPageLanguageLinks(
			$this->newSiteMatrix( self::WIKIS ),
			$this->newInterwikiLookup( [ 'gsw', 'yue' ] ),
			'enwiki'
		);

		$this->assertSame(
			[ 'de:Special:Watchlist', 'fi:Special:Watchlist', 'no:Special:Watchlist' ],
			$links->getLinks( 'Watchlist' )
		);
	}

	public function testNoLinksOnWikiOutsideMatrix() {
		$links = new SpecialPageLanguageLinks(
			$this->newSiteMatrix( self::WIKIS ),
			$this->newInterwikiLookup(),
			'commonswiki'
		);

		$this->assertSame( [], $links->getLinks( 'Watchlist' ) );
	}
}
