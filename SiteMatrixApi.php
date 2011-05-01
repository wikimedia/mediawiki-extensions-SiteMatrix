<?php

if ( !defined( 'MEDIAWIKI' ) ) {
    echo "SiteMatrix extension\n";
    exit( 1 );
}

/**
 * Query module to get site matrix
 * @ingroup API
 */
class ApiQuerySiteMatrix extends ApiQueryBase {

	public function __construct($query, $moduleName) {
		parent :: __construct($query, $moduleName, 'sm');
	}

	public function execute() {
		$result = $this->getResult();
		$matrix = new SiteMatrix();
		$langNames = Language::getLanguageNames();

		$matrix_out = array(
			'count' => $matrix->getCount(),
		);

		if( class_exists( 'LanguageNames' ) ) {
			global $wgLang;
			$localLanguageNames = LanguageNames::getNames( $wgLang->getCode() );
		} else {
			$localLanguageNames = array();
		}

		$params = $this->extractRequestParams();
		$type = array_flip( $params['type'] );
		$state = array_flip( $params['state'] );
	
		$allOrClosed = isset( $state['all'] ) || isset( $state['closed'] );
		$allOrPrivate = isset( $state['all'] ) || isset( $state['private'] );
		$allOrFishbowl = isset( $state['all'] ) || isset( $state['fishbowl'] );

		if ( isset( $type['language'] ) ) {
			foreach ( $matrix->getLangList() as $lang ) {
				$langhost = str_replace( '_', '-', $lang );
				$language = array(
					'code' => $langhost,
					'name' => $langNames[$lang],
					'site' => array(),
				);
				if ( isset( $localLanguageNames[$lang] ) ) {
					$language['localname'] = $localLanguageNames[$lang];
				}

				foreach ( $matrix->getSites() as $site ) {
					if ( $matrix->exist( $lang, $site ) ) {
						$url = $matrix->getUrl( $lang, $site );
						$site_out = array(
							'url' => $url,
							'code' => $site,
						);
						if ( $allOrClosed ) {
							if( $matrix->isClosed( $lang, $site ) ) {
								$site_out['closed'] = '';
							}
						} else {
							continue;
						}
						$language['site'][] = $site_out;
					}
				}

				$result->setIndexedTagName( $language['site'], 'site' );
				$matrix_out[] = $language;
			}
		}

		$result->setIndexedTagName($matrix_out, 'language');
		$result->addValue(null, "sitematrix", $matrix_out);

		if ( isset( $type['special'] ) ) {
			$specials = array();
			foreach ( $matrix->getSpecials() as $special ){
				list( $lang, $site ) = $special;
				$url = $matrix->getUrl( $lang, $site );

				$wiki = array();
				$wiki['url'] = $url;
				$wiki['code'] = str_replace( '_', '-', $lang ) . ( $site != 'wiki' ? $site : '' );

				if( $allOrPrivate ) {
					if ( $matrix->isPrivate( $lang . $site ) ) {
						$wiki['private'] = '';
					}
				} else {
					continue;
				}
				if( $allOrFishbowl ) {
					if ( $matrix->isFishbowl( $lang . $site ) ) {
						$wiki['fishbowl'] = '';
					}
				} else {
					continue;
				}
				if( $allOrClosed ) {
					if ( $matrix->isClosed( $lang, $site ) ) {
						$wiki['closed'] = '';
					}
				} else {
					continue;
				}

				$specials[] = $wiki;
			}

			$result->setIndexedTagName( $specials, 'special' );
			$result->addValue( "sitematrix", "specials", $specials );
		}
	}

	protected function getAllowedParams() {
		return array(
			'type' => array(
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => array(
					'special',
					'language'
				),
				ApiBase::PARAM_DFLT => 'special|language',
			),
		);
	}

	protected function getParamDescription() {
		return array(
			'type' => 'Filter the Site Matrix by wiki type',
		);
	}

	protected function getDescription() {
		return array(
			'Get Wikimedia sites list',
			'The code is either the unique identifier for specials else, for languages, the project code',
			'',
			'Wiki types:',
			' special  - One off, and multilingual Wikimedia projects',
			' language - Wikimedia projects under this language code',
			'Wiki states:',
			' closed   - No write access, full read access',
			' private  - Read and write restricted',
			' fishbowl - Restricted write access, full read access',
			);
	}

	protected function getExamples() {
		return array(
			'api.php?action=sitematrix',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
