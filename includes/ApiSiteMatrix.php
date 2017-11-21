<?php

/**
 * Module to get site matrix
 * @ingroup API
 */
class ApiSiteMatrix extends ApiBase {

	public function __construct( ApiMain $main, $moduleName ) {
		parent::__construct( $main, $moduleName, 'sm' );
	}

	public function execute() {
		$result = $this->getResult();
		$matrix = new SiteMatrix();
		$langNames = Language::fetchLanguageNames();

		$matrix_out = [ 'count' => $matrix->getCount() ];

		$localLanguageNames = Language::fetchLanguageNames( $this->getLanguage()->getCode() );

		$params = $this->extractRequestParams();
		$type = array_flip( $params['type'] );
		$state = array_flip( $params['state'] );
		$langProp = array_flip( $params['langprop'] );
		$siteProp = array_flip( $params['siteprop'] );
		$limit = $params['limit'];
		$continue = isset( $params['continue'] )
			? explode( '|', $params['continue'] )
			: [ 'language', '' ];
		$this->dieContinueUsageIf( count( $continue ) != 2 );

		$all = isset( $state['all'] );
		$closed = isset( $state['closed'] );
		$private = isset( $state['private'] );
		$fishbowl = isset( $state['fishbowl'] );
		$nonglobal = isset( $state['nonglobal'] );

		$count = 0;
		if ( isset( $type['language'] ) && $continue[0] == 'language' ) {
			foreach ( $matrix->getLangList() as $lang ) {
				$langhost = str_replace( '_', '-', $lang );
				if ( $langhost < $continue[1] ) {
					continue;
				}
				if ( $count >= $limit ) {
					$this->setContinueEnumParameter( 'continue', "language|$langhost" );
					break;
				}
				$language = [
					'code' => $langhost,
					'name' => isset( $langNames[$lang] ) ? $langNames[$lang] : null,
					'site' => [],
					'dir' => Language::factory( $langhost )->getDir()
				];
				if ( isset( $localLanguageNames[$lang] ) ) {
					$language['localname'] = $localLanguageNames[$lang];
				}
				$language = array_intersect_key( $language, $langProp );

				if ( isset( $language['site'] ) ) {
					foreach ( $matrix->getSites() as $site ) {
						if ( $matrix->exist( $lang, $site ) ) {
							$skip = true;

							if ( $all ) {
								$skip = false;
							}

							$url = $matrix->getCanonicalUrl( $lang, $site );
							$site_out = [
								'url' => $url,
								'dbname' => $matrix->getDBName( $lang, $site ),
								'code' => $site,
								'sitename' => $matrix->getSitename( $lang, $site ),
							];
							$site_out = array_intersect_key( $site_out, $siteProp );
							if ( $matrix->isClosed( $lang, $site ) ) {
								$site_out['closed'] = true;
								if ( $closed ) {
									$skip = false;
								}
							}

							if ( $skip ) {
								continue;
							}
							$language['site'][] = $site_out;
						}
					}
					$result->setIndexedTagName( $language['site'], 'site' );
				}

				$count++;
				$matrix_out[] = $language;
			}
		}

		$result->setIndexedTagName( $matrix_out, 'language' );
		$result->addValue( null, "sitematrix", $matrix_out );

		if ( isset( $type['special'] ) && $count < $limit ) {
			$specials = [];
			foreach ( $matrix->getSpecials() as $special ) {
				list( $lang, $site ) = $special;
				$dbName = $matrix->getDBName( $lang, $site );
				if ( $continue[0] == 'special' && $dbName < $continue[1] ) {
					continue;
				}
				if ( $count >= $limit ) {
					$this->setContinueEnumParameter( 'continue', "special|$dbName" );
					break;
				}
				$url = $matrix->getCanonicalUrl( $lang, $site );

				$wiki = [];
				$wiki['url'] = $url;
				$wiki['dbname'] = $dbName;
				$wiki['code'] = str_replace( '_', '-', $lang ) . ( $site != 'wiki' ? $site : '' );
				$wiki['sitename'] = $matrix->getSitename( $lang, $site );

				$skip = true;

				if ( $all ) {
					$skip = false;
				}
				if ( $matrix->isPrivate( $lang . $site ) ) {
					$wiki['private'] = true;

					if ( $private ) {
						$skip = false;
					}
				}
				if ( $matrix->isFishbowl( $lang . $site ) ) {
					$wiki['fishbowl'] = true;

					if ( $fishbowl ) {
						$skip = false;
					}
				}
				if ( $matrix->isNonGlobal( $lang . $site ) ) {
					$wiki['nonglobal'] = true;

					if ( $nonglobal ) {
						$skip = false;
					}
				}
				if ( $matrix->isClosed( $lang, $site ) ) {
					$wiki['closed'] = true;

					if ( $closed ) {
						$skip = false;
					}
				}

				if ( $skip ) {
					continue;
				}

				$specials[] = $wiki;
			}

			$result->setIndexedTagName( $specials, 'special' );
			$result->addValue( "sitematrix", "specials", $specials );
		}
	}

	protected function setContinueEnumParameter( $paramName, $paramValue ) {
		$paramName = $this->encodeParamName( $paramName );
		$msg = [ $paramName => $paramValue ];
		$result = $this->getResult();
		$result->addValue( 'query-continue', $this->getModuleName(), $msg, ApiResult::NO_SIZE_CHECK );
	}

	public function getAllowedParams() {
		return [
			'type' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'special',
					'language'
				],
				ApiBase::PARAM_DFLT => 'special|language',
			],
			'state' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'all',
					'closed',
					'private',
					'fishbowl',
					'nonglobal',
				],
				ApiBase::PARAM_DFLT => 'all',
			],
			'langprop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'code',
					'name',
					'site',
					'dir',
					'localname',
				],
				ApiBase::PARAM_DFLT => 'code|name|site|dir|localname',
			],
			'siteprop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'url',
					'dbname',
					'code',
					'sitename',
				],
				ApiBase::PARAM_DFLT => 'url|dbname|code|sitename',
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 5000,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 5000,
				ApiBase::PARAM_MAX2 => 5000,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [ 'action=sitematrix' => 'apihelp-sitematrix-example-1', ];
	}
}
