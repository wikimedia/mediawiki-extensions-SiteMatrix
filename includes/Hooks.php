<?php

namespace MediaWiki\Extension\SiteMatrix;

use ApiQuerySiteinfo;
use MediaWiki\Api\Hook\APIQuerySiteInfoGeneralInfoHook;
use MediaWiki\Hook\MagicWordwgVariableIDsHook;
use MediaWiki\Hook\ParserGetVariableValueSwitchHook;
use Parser;
use PPFrame;

/**
 * Hook handlers
 */
class Hooks implements
	APIQuerySiteInfoGeneralInfoHook,
	ParserGetVariableValueSwitchHook,
	MagicWordwgVariableIDsHook
{
	/**
	 * Handler method for the APISiteInfoGeneralInfo hook
	 *
	 * @param ApiQuerySiteinfo $module
	 * @param array &$results
	 */
	public function onAPIQuerySiteInfoGeneralInfo( $module, &$results ) {
		global $wgDBname, $wgConf;

		$matrix = new SiteMatrix();

		[ $site, $lang ] = $wgConf->siteFromDB( $wgDBname );
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
	 * @param PPFrame $frame
	 */
	public function onParserGetVariableValueSwitch(
		$parser,
		&$variableCache,
		$magicWordId,
		&$ret,
		$frame
	) {
		if ( $magicWordId === 'numberofwikis' ) {
			global $wgLocalDatabases;
			$ret = $variableCache[$magicWordId] = count( $wgLocalDatabases );
		}
	}

	/**
	 * @param string[] &$customVariableIds
	 */
	public function onMagicWordwgVariableIDs( &$customVariableIds ) {
		$customVariableIds[] = 'numberofwikis';
	}
}
