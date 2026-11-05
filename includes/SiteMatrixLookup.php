<?php

namespace MediaWiki\Extension\SiteMatrix;

use Generator;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Site\SiteList;
use MediaWiki\Site\SiteLookup;

class SiteMatrixLookup implements SiteLookup {
	private const SCRIPT_PATH = '/w/$1';
	private const ARTICLE_PATH = '/wiki/$1';

	/** @var MediaWikiSite[] */
	private array $sites;

	public function __construct( private SiteMatrix $siteMatrix, private string $localGroup ) {
		$this->sites = [];
		foreach ( $this->enumerateSites() as $site ) {
			$this->sites[ $site->getGlobalId() ] = $site;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getSite( string $globalId ): ?MediaWikiSite {
		return $this->sites[ $globalId ] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSites(): SiteList {
		return new SiteList( $this->sites );
	}

	/**
	 * Build all sites available in SiteMatrix
	 *
	 * @return Generator<MediaWikiSite>
	 */
	private function enumerateSites(): Generator {
		foreach ( $this->siteMatrix->getLangList() as $lang ) {
			foreach ( $this->siteMatrix->getSites() as $group ) {
				if ( $this->siteMatrix->exist( $lang, $group ) ) {
					yield $this->buildSite( $lang, $group );
				}
			}
		}

		foreach ( $this->siteMatrix->getSpecials() as [ $lang, $group ] ) {
			yield $this->buildSite( $lang, $group, true );
		}
	}

	/**
	 * Build and return a single Site object
	 */
	private function buildSite( string $lang, string $group, bool $isSpecial = false ): MediaWikiSite {
		$site = new MediaWikiSite();
		$site->setGlobalId( $this->siteMatrix->getDBName( $lang, $group ) );

		$url = $this->siteMatrix->getCanonicalUrl( $lang, $group );
		$site->setFilePath( $url . self::SCRIPT_PATH );
		$site->setPagePath( $url . self::ARTICLE_PATH );

		if ( $isSpecial ) {
			$langCode = $this->siteMatrix->getLanguageCode( $lang, $group );
			$dashMinor = str_replace( '_', '-', $lang );
			$site->setGroup( $dashMinor . ( $group != 'wiki' ? $group : '' ) );
			$site->setLanguageCode( $langCode );
			$site->addInterwikiId( $dashMinor . ( $group != 'wiki' ? $group : '' ) );
		} else {
			$langHost = LanguageCode::replaceDeprecatedCodes( str_replace( '_', '-', $lang ) );
			$longGroup = ( $group === 'wiki' ? 'wikipedia' : $group );
			$site->setGroup( $longGroup );
			$site->setLanguageCode( $langHost );

			if ( $longGroup === $this->localGroup ) {
				$site->addNavigationId( $langHost );
				$site->addInterwikiId( $langHost );
			}
		}

		return $site;
	}
}
