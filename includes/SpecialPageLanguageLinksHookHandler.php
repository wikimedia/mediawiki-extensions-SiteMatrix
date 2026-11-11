<?php

namespace MediaWiki\Extension\SiteMatrix;

use MediaWiki\Config\Config;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\SpecialPage\SpecialPageFactory;

/**
 * Adds language links to special pages (T123063).
 */
class SpecialPageLanguageLinksHookHandler implements BeforePageDisplayHook {

	public function __construct(
		private readonly Config $config,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly InterwikiLookup $interwikiLookup,
	) {
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->get( 'SiteMatrixSpecialPageLanguageLinks' ) ) {
			return;
		}

		$title = $out->getTitle();
		if ( !$title || !$title->isSpecialPage() ) {
			return;
		}

		[ $canonicalName ] = $this->specialPageFactory->resolveAlias( $title->getDBkey() );
		if ( $canonicalName === null ) {
			return;
		}

		$existingTargets = [];
		foreach ( $out->getLanguageLinks() as $link ) {
			$existingTargets[] = $this->getLinkTarget( $link );
		}

		$languageLinks = new SpecialPageLanguageLinks(
			// Fetched lazily: building SiteMatrix is costly to do on every page view
			MediaWikiServices::getInstance()->getService( 'SiteMatrix' ),
			$this->interwikiLookup,
			$this->config->get( MainConfigNames::DBname )
		);

		$newLinks = $languageLinks->getLinks( $canonicalName );
		if ( $existingTargets ) {
			$newLinks = array_filter(
				$newLinks,
				fn ( $link ) => !in_array( $this->getLinkTarget( $link ), $existingTargets, true )
			);
		}
		$out->addLanguageLinks( array_values( $newLinks ) );
	}

	/**
	 * Compare by interwiki URL, as different prefixes can point to the same wiki (e.g. als and gsw).
	 *
	 * @param string $link Language link in "prefix:Title" format
	 * @return string Interwiki URL, or the prefix if it is not a known interwiki
	 */
	private function getLinkTarget( string $link ): string {
		$prefix = explode( ':', $link, 2 )[0];
		$interwiki = $this->interwikiLookup->fetch( $prefix );
		return $interwiki ? $interwiki->getURL() : $prefix;
	}
}
