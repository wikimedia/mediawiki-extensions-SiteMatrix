<?php

namespace MediaWiki\Extension\SiteMatrix;

use ApiBase;
use ApiMain;
use ApiResult;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Languages\LanguageNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Module to get site matrix
 * @ingroup API
 */
class ApiSiteMatrix extends ApiBase {

	/** @var LanguageNameUtils */
	private $languageNameUtils;

	/** @var LanguageFactory */
	private $languageFactory;

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @param LanguageNameUtils $languageNameUtils
	 * @param LanguageFactory $languageFactory
	 */
	public function __construct(
		ApiMain $main,
		$moduleName,
		LanguageNameUtils $languageNameUtils,
		LanguageFactory $languageFactory
	) {
		parent::__construct( $main, $moduleName, 'sm' );
		$this->languageNameUtils = $languageNameUtils;
		$this->languageFactory = $languageFactory;
	}

	public function execute() {
		$result = $this->getResult();
		$matrix = new SiteMatrix();
		$langNames = $this->languageNameUtils->getLanguageNames();

		$matrix_out = [ 'count' => $matrix->getCount() ];

		$localLanguageNames = $this->languageNameUtils->getLanguageNames( $this->getLanguage()->getCode() );

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
		'@phan-var array{string,string} $continue';

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
					'name' => $langNames[$lang] ?? null,
					'site' => [],
					'dir' => $this->languageFactory->getLanguage( $langhost )->getDir()
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
								'lang' => $matrix->getLanguageCode( $lang, $site ),
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
				$wiki['lang'] = $matrix->getLanguageCode( $lang, $site );
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
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'special',
					'language'
				],
				ParamValidator::PARAM_DEFAULT => 'special|language',
			],
			'state' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'all',
					'closed',
					'private',
					'fishbowl',
					'nonglobal',
				],
				ParamValidator::PARAM_DEFAULT => 'all',
			],
			'langprop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'code',
					'name',
					'site',
					'dir',
					'localname',
				],
				ParamValidator::PARAM_DEFAULT => 'code|name|site|dir|localname',
			],
			'siteprop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'url',
					'dbname',
					'code',
					'lang',
					'sitename',
				],
				ParamValidator::PARAM_DEFAULT => 'url|dbname|code|sitename',
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 5000,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 5000,
				IntegerDef::PARAM_MAX2 => 5000,
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
