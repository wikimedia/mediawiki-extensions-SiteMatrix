<?php

namespace MediaWiki\Extension\SiteMatrix;

use InvalidArgumentException;
use LanguageCode;
use MediaWiki\MediaWikiServices;

/**
 * Service to access
 */
class SiteMatrix {
	/** @var string[] Language codes used by this wikifarm, sorted alphabetically. */
	protected $langlist;

	/**
	 * Sites (aka project families) used by this wikifarm. These will be things like 'wiktionary'.
	 * @var string[]
	 * @see $wgSiteMatrixSites
	 */
	protected $sites;

	/**
	 * Human-readable site names. (Except they aren't...)
	 * @var string[] site => name<br/>iw-prefix
	 * @see $wgSiteMatrixSites
	 */
	protected $names;

	/**
	 * Wiki family domain names.
	 * @var string[] site => host
	 * @see $wgSiteMatrixSites
	 */
	protected $hosts;

	/** @var string[]|null Lazy-loaded dbname list of private wikis. */
	protected $private;

	/** @var string[]|null Lazy-loaded dbname list of fishbowl wikis. */
	protected $fishbowl;

	/** @var string[]|null Lazy-loaded dbname list of closed wikis. */
	protected $closed;

	/** @var string[]|null Lazy-loaded dbname list of non-SUL wikis. */
	protected $nonglobal;

	/**
	 * Special wikis (which are multilingual or otherwise not split by language),
	 * partially sorted by language.
	 * Language codes use _ instead of -.
	 * @var array[] [ <language code>, <family> ]
	 */
	protected $specials;

	/**
	 * A matrix of which wikis exist in which language.
	 * Language codes use _ instead of -.
	 * @var array[] site => language => 1
	 */
	protected $matrix;

	/** @var int Total number of wikis. */
	protected $count;

	/**
	 * The number of wikis in each wiki family.
	 * @var int[] site => count
	 */
	protected $countPerSite;

	/**
	 * Create and load the site matrix.
	 * Goes through $wgLocalDatabases and uses a bunch of $wgSiteMatrix* settings to
	 * sort it into project families and special projects.
	 */
	public function __construct() {
		global $wgSiteMatrixFile, $wgSiteMatrixSites;
		global $wgLocalDatabases, $wgConf;

		$wgConf->loadFullData();

		if ( $wgSiteMatrixFile !== null && file_exists( $wgSiteMatrixFile ) ) {
			$this->langlist = $this->extractFile( $wgSiteMatrixFile );
			$hideEmpty = false;
		} else {
			$this->langlist = array_keys(
				MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageNames()
			);
			$hideEmpty = true;
		}

		sort( $this->langlist );
		$xLanglist = array_flip( $this->langlist );

		$this->sites = [];
		$this->names = [];
		$this->hosts = [];

		foreach ( $wgSiteMatrixSites as $site => $conf ) {
			$this->sites[] = $site;
			$this->names[$site] = $conf['name'] . ( isset( $conf['prefix'] ) ?
				'<br />' . $conf['prefix'] : '' );
			$this->hosts[$site] = $conf['host'];
		}

		# Initialize $countPerSite
		$this->countPerSite = [];
		foreach ( $this->sites as $site ) {
			$this->countPerSite[$site] = 0;
		}

		# Tabulate the matrix
		$this->specials = [];
		$this->matrix = [];
		foreach ( $wgLocalDatabases as $db ) {
			# Find suffix
			$found = false;
			foreach ( $this->sites as $site ) {
				$m = [];
				if ( preg_match( "/(.*)$site\$/", $db, $m ) ) {
					$lang = $m[1];
					$langhost = str_replace( '_', '-', $lang );
					if ( isset( $xLanglist[$langhost] ) ) {
						$this->matrix[$site][$langhost] = 1;
						$this->countPerSite[$site]++;
					} else {
						$this->specials[] = [ $lang, $site ];
					}
					$found = true;
					break;
				}
			}
			if ( !$found ) {
				list( $major, $minor ) = $wgConf->siteFromDB( $db );
				if ( $major !== null ) {
					$this->specials[] = [ str_replace( '-', '_', $minor ), $major ];
				}
			}
		}

		uasort( $this->specials, static function ( $a1, $a2 ) {
			return strcmp( $a1[0], $a2[0] );
		} );

		if ( $hideEmpty ) {
			foreach ( $xLanglist as $lang => $_ ) {
				$empty = true;
				foreach ( $this->sites as $site ) {
					if ( !empty( $this->matrix[$site][$lang] ) ) {
						$empty = false;
					}
				}
				if ( $empty ) {
					unset( $xLanglist[$lang] );
				}
			}
			$this->langlist = array_keys( $xLanglist );
		}

		$this->count = count( $wgLocalDatabases );
	}

	/**
	 * Get language codes used by this wikifarm (sorted alphabetically).
	 * @return string[]
	 */
	public function getLangList() {
		return $this->langlist;
	}

	/**
	 * Get family names in an almost-human-readable format (will be something like 'Wikipedia<br/>w').
	 * @return string[] family => name
	 */
	public function getNames() {
		return $this->names;
	}

	/**
	 * Get the list of project families used by this wikifarm.
	 * @return string[]
	 */
	public function getSites() {
		return $this->sites;
	}

	/**
	 * Get list of special wikis (which are multilingual or otherwise not split by language),
	 * partially sorted by language.
	 * Language codes use _ instead of -.
	 * @return array[] [ <language code>, <family> ]
	 */
	public function getSpecials() {
		return $this->specials;
	}

	/**
	 * Get the total number of wikis.
	 * @return int
	 */
	public function getCount() {
		return $this->count;
	}

	/**
	 * Get the total number of wikis in a wiki family.
	 * @param string $site
	 * @return int
	 */
	public function getCountPerSite( $site ) {
		return $this->countPerSite[$site];
	}

	/**
	 * Get the base URL of a wiki family (e.g. '//www.wikipedia.org/') with trailing /.
	 * @param string $site
	 * @return string
	 */
	public function getSiteUrl( $site ) {
		return '//' . $this->hosts[$site] . '/';
	}

	/**
	 * Get the base URL of a wiki (as in $wgServer / $wgCanonicalServer).
	 * @param string $minor Language
	 * @param string $major Site
	 * @param bool $canonical use canonical url.
	 * @return mixed
	 */
	public function getUrl( $minor, $major, $canonical = false ) {
		return $this->getSetting(
			$canonical ? 'wgCanonicalServer' : 'wgServer',
			$minor,
			$major
		);
	}

	/**
	 * Shortcut for getUrl( $minor, $major, true ).
	 * @param string $minor Language
	 * @param string $major Site
	 * @return mixed
	 */
	public function getCanonicalUrl( $minor, $major ) {
		return $this->getSetting( 'wgCanonicalServer', $minor, $major );
	}

	/**
	 * Get human-readable name of a wiki.
	 * @param string $minor
	 * @param string $major
	 * @return string
	 */
	public function getSitename( $minor, $major ) {
		return $this->getSetting( 'wgSitename', $minor, $major );
	}

	/**
	 * Get the normalised IETF language tag.
	 *
	 * @param string $minor
	 * @param string $major
	 * @return string
	 */
	public function getLanguageCode( $minor, $major ) {
		return LanguageCode::bcp47( $this->getSetting( 'wgLanguageCode', $minor, $major ) );
	}

	/**
	 * @param string $setting setting name
	 * @param string $lang language subdomain
	 * @param string $dbSuffix e.g. 'wiki' for 'enwiki' or 'wikisource' for 'enwikisource'
	 * @return mixed
	 */
	private function getSetting( $setting, $lang, $dbSuffix ) {
		global $wgConf;

		$dbname = $this->getDBName( $lang, $dbSuffix );

		list( $major, $minor ) = $wgConf->siteFromDB( $dbname );
		if ( $major === null ) {
			throw new InvalidArgumentException( "Invalid DB name \"$dbname\"" );
		}
		$minor = str_replace( '_', '-', $minor );

		return $wgConf->get(
			$setting,
			$dbname,
			$major,
			[ 'lang' => $minor, 'site' => $major ]
		);
	}

	/**
	 * @param string $minor
	 * @param string $major
	 * @return string
	 */
	public function getDBName( $minor, $major ) {
		return str_replace( '-', '_', $minor ) . $major;
	}

	/**
	 * Check whether a wiki exists.
	 * @param string $minor Language
	 * @param string $major Site
	 * @return bool
	 */
	public function exist( $minor, $major ) {
		return !empty( $this->matrix[$major][$minor] );
	}

	/**
	 * Check whether a wiki is closed (not editable).
	 * @param string $minor Language
	 * @param string $major Site
	 * @return bool
	 */
	public function isClosed( $minor, $major ) {
		global $wgSiteMatrixClosedSites;

		$dbname = $this->getDBName( $minor, $major );

		if ( $wgSiteMatrixClosedSites === null ) {
			// Fallback to old behavior checking read-only settings;
			// not very reliable.
			global $wgConf;

			list( $major, $minor ) = $wgConf->siteFromDB( $dbname );
			if ( $major === null ) {
				// No such suffix
				return false;
			}

			if ( $wgConf->get( 'wgReadOnly', $dbname, $major, [ 'site' => $major, 'lang' => $minor ] ) ) {
				return true;
			}
			$readOnlyFile = $wgConf->get( 'wgReadOnlyFile',
				$dbname,
				$major,
				[ 'site' => $major, 'lang' => $minor ]
			);
			if ( $readOnlyFile && file_exists( $readOnlyFile ) ) {
				return true;
			}
			return false;
		}

		if ( $this->closed == null ) {
			$this->closed = $this->extractDbList( $wgSiteMatrixClosedSites );
		}
		return in_array( $dbname, $this->closed );
	}

	/**
	 * Check whether a wiki is private (not publicly readable).
	 * @param string $dbname DatabaseName
	 * @return bool
	 */
	public function isPrivate( $dbname ) {
		global $wgSiteMatrixPrivateSites;

		if ( $this->private == null ) {
			$this->private = $this->extractDbList( $wgSiteMatrixPrivateSites );
		}

		return in_array( $dbname, $this->private );
	}

	/**
	 * Check whether a wiki is a fishbowl (publicly readable but not publicly editable).
	 * @param string $dbname DatabaseName
	 * @return bool
	 */
	public function isFishbowl( $dbname ) {
		global $wgSiteMatrixFishbowlSites;

		if ( $this->fishbowl == null ) {
			$this->fishbowl = $this->extractDbList( $wgSiteMatrixFishbowlSites );
		}

		return in_array( $dbname, $this->fishbowl );
	}

	/**
	 * Check whether a wiki is non-global (not using single sign-on).
	 * @param string $dbname DatabaseName
	 * @return bool
	 */
	public function isNonGlobal( $dbname ) {
		global $wgSiteMatrixNonGlobalSites;

		if ( $this->nonglobal == null ) {
			$this->nonglobal = $this->extractDbList( $wgSiteMatrixNonGlobalSites );
		}

		return in_array( $dbname, $this->nonglobal );
	}

	/**
	 * Check whether a wiki is special (the only wiki in its wiki family; typically this means
	 * a multilingual wiki).
	 * @param string $dbname DatabaseName
	 * @return bool
	 */
	public function isSpecial( $dbname ) {
		return in_array( $dbname, $this->specials );
	}

	/**
	 * Pull a list of dbnames from a given text file, or pass through an array.
	 * Used for the DB list configuration settings.
	 *
	 * @param string[]|string $listOrFilename Array of strings, or string with a filename
	 * @return string[]
	 */
	private function extractDbList( $listOrFilename ) {
		if ( is_string( $listOrFilename ) ) {
			return $this->extractFile( $listOrFilename );
		} elseif ( is_array( $listOrFilename ) ) {
			return $listOrFilename;
		} else {
			return [];
		}
	}

	/**
	 * Pull a list of dbnames from a given text file.
	 *
	 * @param string $filename
	 * @return string[]
	 */
	private function extractFile( $filename ) {
		return array_map( 'trim', file( $filename ) );
	}
}
