<?php

namespace MediaWiki\Extension\SiteMatrix;

use MediaWiki\Interwiki\InterwikiLookup;

/**
 * Builds language links from a special page to the same special page on the
 * other language wikis of the current wiki's project family.
 */
class SpecialPageLanguageLinks {

	public function __construct(
		private readonly SiteMatrix $siteMatrix,
		private readonly InterwikiLookup $interwikiLookup,
		private readonly string $localDBName,
	) {
	}

	/**
	 * @param string $canonicalName Canonical name of the special page, without subpage
	 * @return string[] Language links in "prefix:Special:Name" format
	 */
	public function getLinks( string $canonicalName ): array {
		$links = [];
		foreach ( $this->getSiblingPrefixes() as $prefix ) {
			$links[] = "$prefix:Special:$canonicalName";
		}
		return $links;
	}

	/**
	 * @return string[]
	 */
	private function getSiblingPrefixes(): array {
		$local = $this->findLocalWiki();
		if ( $local === null ) {
			// Special (multilingual) wiki, or a wiki not in the matrix
			return [];
		}
		[ $localLang, $family ] = $local;

		$prefixes = [];
		foreach ( $this->siteMatrix->getLangList() as $lang ) {
			if ( $lang === $localLang || !$this->siteMatrix->exist( $lang, $family ) ) {
				continue;
			}
			$dbName = $this->siteMatrix->getDBName( $lang, $family );
			if (
				$this->siteMatrix->isClosed( $lang, $family ) ||
				$this->siteMatrix->isPrivate( $dbName )
			) {
				continue;
			}
			// Matrix language keys are what SiteMatrixLookup uses as interwiki IDs
			if ( !$this->interwikiLookup->isValidInterwiki( $lang ) ) {
				continue;
			}
			$prefixes[] = $lang;
		}

		return $prefixes;
	}

	/**
	 * @return string[]|null [ language key, family ], or null if not found
	 */
	private function findLocalWiki(): ?array {
		foreach ( $this->siteMatrix->getSites() as $family ) {
			foreach ( $this->siteMatrix->getLangList() as $lang ) {
				if (
					$this->siteMatrix->exist( $lang, $family ) &&
					$this->siteMatrix->getDBName( $lang, $family ) === $this->localDBName
				) {
					return [ $lang, $family ];
				}
			}
		}
		return null;
	}
}
