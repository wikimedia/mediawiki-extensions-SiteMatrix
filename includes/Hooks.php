<?php

namespace MediaWiki\Extension\SiteMatrix;

use ApiQuerySiteinfo;
use Parser;
use PPFrame;

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
	 * @param array &$cache
	 * @param string &$magicWordId
	 * @param string &$ret
	 * @param PPFrame|null $frame
	 */
	public static function onParserGetVariableValueSwitch(
		Parser $parser,
		&$cache,
		&$magicWordId,
		&$ret,
		$frame = null ) {
		if ( $magicWordId == 'numberofwikis' ) {
			global $wgLocalDatabases;
			$ret = count( $wgLocalDatabases );
		}
	}

	/**
	 * @param string[] &$customVariableIds
	 */
	public static function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'numberofwikis';
	}
}
