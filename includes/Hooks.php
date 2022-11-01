<?php

namespace MediaWiki\Extension\SiteMatrix;

use ApiQuerySiteinfo;
use Parser;

/**
 * Hook handlers
 */
class Hooks {
	/**
	 * Handler method for the APISiteInfoGeneralInfo hook
	 *
	 * @param ApiQuerySiteinfo $module
	 * @param array &$results
	 */
	public static function onAPIQuerySiteInfoGeneralInfo( $module, &$results ) {
		global $wgDBname, $wgConf;

		$matrix = new SiteMatrix();

		list( $site, $lang ) = $wgConf->siteFromDB( $wgDBname );
		if ( $site === null ) {
			// No such site
			return;
		}

		if ( $matrix->isClosed( $lang, $site ) ) {
			$results['closed'] = '';
		}

		if ( $matrix->isSpecial( $wgDBname ) ) {
			$results['special'] = '';
		}

		if ( $matrix->isPrivate( $wgDBname ) ) {
			$results['private'] = '';
		}

		if ( $matrix->isFishbowl( $wgDBname ) ) {
			$results['fishbowl'] = '';
		}

		if ( $matrix->isNonGlobal( $wgDBname ) ) {
			$results['nonglobal'] = '';
		}
	}

	/**
	 * @param Parser $parser
	 * @param array &$variableCache
	 * @param string $magicWordId
	 * @param string &$ret
	 */
	public static function onParserGetVariableValueSwitch(
		$parser,
		&$variableCache,
		$magicWordId,
		&$ret
	) {
		if ( $magicWordId === 'numberofwikis' ) {
			global $wgLocalDatabases;
			$ret = $variableCache[$magicWordId] = count( $wgLocalDatabases );
		}
	}

	/**
	 * @param string[] &$customVariableIds
	 */
	public static function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'numberofwikis';
	}
}
