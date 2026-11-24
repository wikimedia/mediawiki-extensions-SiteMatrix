<?php

namespace MediaWiki\Extension\SiteMatrix;

use MediaWiki\Context\RequestContext;
use MediaWiki\Interwiki\Interwiki;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\SiteMatrix\SpecialPageLanguageLinksHookHandler
 */
class SpecialPageLanguageLinksHookHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'SiteMatrixSpecialPageLanguageLinks' => true,
			MainConfigNames::LanguageCode => 'en',
		] );

		// The local wiki is "en" in the "wiki" family, with "de" and "fi" as siblings
		$localDBName = $this->getConfVar( MainConfigNames::DBname );
		$dbNames = [ 'de' => 'dewiki', 'en' => $localDBName, 'fi' => 'fiwiki' ];
		$siteMatrix = $this->createMock( SiteMatrix::class );
		$siteMatrix->method( 'getSites' )->willReturn( [ 'wiki' ] );
		$siteMatrix->method( 'getLangList' )->willReturn( array_keys( $dbNames ) );
		$siteMatrix->method( 'exist' )->willReturn( true );
		$siteMatrix->method( 'getDBName' )->willReturnCallback(
			static fn ( $lang ) => $dbNames[$lang]
		);
		$this->setService( 'SiteMatrix', $siteMatrix );
	}

	private function runHook( string $titleText, array $existingLinks = [] ): array {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $titleText ) );
		$out = new OutputPage( $context );
		$out->addLanguageLinks( $existingLinks );

		$interwikiLookup = $this->createMock( InterwikiLookup::class );
		$interwikiLookup->method( 'isValidInterwiki' )->willReturn( true );
		// "deu" is an alias of "de", like "als" and "gsw" in production
		$interwikiLookup->method( 'fetch' )->willReturnCallback( static fn ( $prefix ) => new Interwiki(
			$prefix,
			'https://' . ( $prefix === 'deu' ? 'de' : $prefix ) . '.wikipedia.org/wiki/$1'
		) );

		$handler = new SpecialPageLanguageLinksHookHandler(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getSpecialPageFactory(),
			$interwikiLookup,
			$this->getServiceContainer()->getLanguageFactory()
		);
		$handler->onBeforePageDisplay( $out, $context->getSkin() );

		return $out->getLanguageLinks();
	}

	public function testAddsLinksToSpecialPage() {
		$this->assertSame(
			[ 'de:Special:Watchlist', 'fi:Special:Watchlist' ],
			$this->runHook( 'Special:Watchlist' )
		);
	}

	public function testDropsSubpage() {
		$this->assertSame(
			[ 'de:Special:Contributions', 'fi:Special:Contributions' ],
			$this->runHook( 'Special:Contributions/Example' )
		);
	}

	public function testUsesCanonicalNameForLocalizedAlias() {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'de' );

		$this->assertSame(
			[ 'de:Special:Watchlist', 'fi:Special:Watchlist' ],
			$this->runHook( 'Spezial:Beobachtungsliste' )
		);
	}

	public function testDoesNotDuplicateExistingLinks() {
		$this->assertSame(
			[ 'de:Special:Beobachtungsliste', 'fi:Special:Watchlist' ],
			$this->runHook( 'Special:Watchlist', [ 'de:Special:Beobachtungsliste' ] )
		);
	}

	public function testDoesNotDuplicateLinksWithAliasPrefix() {
		$this->assertSame(
			[ 'deu:Special:Beobachtungsliste', 'fi:Special:Watchlist' ],
			$this->runHook( 'Special:Watchlist', [ 'deu:Special:Beobachtungsliste' ] )
		);
	}

	public function testNoLinksWhenDisabled() {
		$this->overrideConfigValue( 'SiteMatrixSpecialPageLanguageLinks', false );

		$this->assertSame( [], $this->runHook( 'Special:Watchlist' ) );
	}

	public function testUsesPreferredEnglishName() {
		$this->assertSame(
			[ 'de:Special:RecentChanges', 'fi:Special:RecentChanges' ],
			$this->runHook( 'Special:RecentChanges' )
		);
	}

	public function testNoLinksOnOtherPages() {
		$this->assertSame( [], $this->runHook( 'Main Page' ) );
	}

	public function testNoLinksOnUnknownSpecialPage() {
		$this->assertSame( [], $this->runHook( 'Special:ThisPageDoesNotExist' ) );
	}
}
