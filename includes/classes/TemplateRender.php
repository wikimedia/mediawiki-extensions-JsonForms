<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;
use MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine;
use MediaWiki\Parser\Parser;

class TemplateRender {
	private Parser $parser;

	public function __construct( Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * @param stdClass $data
	 * @param string $templatePrefix
	 * @return string
	 */
	public function render( $data, $templatePrefix ) {
		$childrenHtml = $this->renderNode( $data, [], [], $templatePrefix );

		// root container
		$params = [
			"children" => $childrenHtml,
			"count" => is_array( $data )
				? count( $data )
				: ( is_object( $data )
					? count( (array)$data )
					: 1 ),
			// 'data' => $data,
			"key" => "",
			"path" => "",
			"type" => gettype( $data ),
			"hasChildren" => !empty( $childrenHtml ),
			'templateName' => $templatePrefix
		];

		$params_ = $params;
		$params_['data'] = $data;
		unset( $params_['children'] );

		$params["params"] = "<pre>" .
						json_encode(
							$params_,
							JSON_PRETTY_PRINT |
								JSON_UNESCAPED_UNICODE |
								JSON_UNESCAPED_SLASHES,
						) .
						"</pre>";

		return $this->processTemplate( $templatePrefix, $params );
	}

	/**
	 * @param array $node
	 * @param string $path
	 * @param string $pathNoIndex
	 * @param string $templatePrefix
	 * @return string
	 */
	public function renderNode( $node, $path, $pathNoIndex, $templatePrefix ) {
		$ret = "";

		foreach ( $node as $key => $value ) {
			$newPath = $path;
			$newPathNoIndex = $pathNoIndex;

			// Only add non-numeric keys to the path
			$newPath[] = $key;

			if ( !is_numeric( $key ) && is_string( $key ) ) {
				$newPathNoIndex[] = $key;
			} else {
				$newPathNoIndex[] = "Item";
			}

			$pathStr = implode( ".", $newPath );
			$pathStrNoIndex = implode( ".", $newPathNoIndex );

			$templateName = $templatePrefix;
			if ( !empty( $newPath ) ) {
				$templateName .= "." . $pathStrNoIndex;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				$childrenHtml = $this->renderNode(
					$value,
					$newPath,
					$newPathNoIndex,
					$templatePrefix,
				);

				$params = [
					"key" => $key,
					"children" => $childrenHtml,
					"count" => is_array( $value )
						? count( $value )
						: count( (array)$value ),
					"hasChildren" => true,
					"path" => $pathStr,
					'templateName' => $templateName,
					// 'value' => $value
				];

				$dataArray = is_array( $value ) ? $value : (array)$value;
				foreach ( $dataArray as $propKey => $propValue ) {
					// Only include scalar values (strings, numbers, booleans, null)
					if ( is_scalar( $propValue ) ) {
						$params[$propKey] = (string)$propValue;
					}
				}

				$params_ = $params;
				unset( $params_['children'] );

				$params["params"] =
						"<pre>" .
						json_encode(
							$params_,
							JSON_PRETTY_PRINT |
								JSON_UNESCAPED_UNICODE |
								JSON_UNESCAPED_SLASHES,
						) .
						"</pre>";

				// var_dump($templateName);
				//  print_r( $params);

				$ret .= $this->processTemplate( $templateName, $params );

			// Leaf
			} else {
				$params = [
					"key" => $key,
					"path" => $pathStr,
					"value" => $value,
					"type" => gettype( $value ),
					"hasChildren" => false,
					'templateName' => $templateName,
					"params" => $value,
				];

				$titleTemplate = TitleClass::newFromText(
					$templateName,
					NS_TEMPLATE,
				);

				if ( $titleTemplate && $titleTemplate->isKnown() ) {
					$ret .= $this->processTemplate( $templateName, $params );
				} else {
					$ret .= $value;
				}

				// var_dump($templateName);
			}
		}

		return $ret;
	}

	/**
	 * credits Filburt
	 * Processes a template call with parameters for VisualData extension.
	 *
	 * This method extracts template parameters from a string, merges them with existing parameters,
	 * and expands the template using either Scribunto's Lua engine or the fallback method.
	 *
	 * @param string $titleStr The template name and additional parameters in the format
	 * "|template=My Template{{!}}param1=value1{{!}}param2=value2" for named parameters or
	 * "|template=My Template{{!}}value1{{!}}value2" for unnamed parameters.
	 * @param array $params Associative array of parameters from the VisualData query.
	 * @return string The expanded template content or a link to the template if it doesn't exist.
	 *
	 * details
	 * - Supports both named parameters (param=value) and unnamed parameters (value).
	 * - Named parameters are passed as-is, unnamed parameters are assigned numeric keys starting from 1.
	 * - If the template doesn't exist, a simple wiki link is returned.
	 * - Uses Scribunto's Lua engine if available, otherwise falls back to expandTemplate.
	 * - All parameter keys are cast to strings to ensure consistency.
	 */
	public function processTemplate( $titleStr, $params ) {
		$templateParts = explode( "|", $titleStr, 2 );
		$templateName = trim( $templateParts[0] );
		$additionalParamsString = $templateParts[1] ?? "";

		$additionalParams = [];
		if ( !empty( $additionalParamsString ) ) {
			$paramPairs = explode( "|", $additionalParamsString );
			foreach ( $paramPairs as $index => $pair ) {
				// Named parameters
				if ( strpos( $pair, "=" ) !== false ) {
					$kv = explode( "=", $pair, 2 );
					$additionalParams[trim( $kv[0] )] = trim( $kv[1] );

				// Unnamed parameters
				} else {
					$additionalParams[(string)( $index + 1 )] = trim( $pair );
				}
			}
		}

		$mergedParams = [];
		foreach ( $params as $key => $value ) {
			$mergedParams[(string)$key] = $value;
		}

		foreach ( $additionalParams as $key => $value ) {
			$mergedParams[(string)$key] = $value;
		}

		$titleTemplate = TitleClass::newFromText( $templateName, NS_TEMPLATE );

		if ( !$titleTemplate || !$titleTemplate->isKnown() ) {
			return "[[$templateName]]";
		}

		$args = $mergedParams;
		$titleText = $titleTemplate->getText();

		// @see \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine::expandTemplate
		if (
			class_exists(
				"MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine",
			)
		) {
			$luaStandaloneEngine = new LuaStandaloneEngine( [
				"parser" => $this->parser,
				"title" => $this->parser->getTitle(),
			] );
			$frameId = "empty";
			$text = $luaStandaloneEngine->expandTemplate(
				$frameId,
				$titleText,
				$args,
			);
			return $text[0] ?? "";
		}

		return self->expandTemplate( $titleTemplate, $args );
	}

	/**
	 * @see \MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaEngine
	 * @param Title|MediaWiki\Title\Title $title
	 * @param array $args
	 * @return array
	 */
	public function expandTemplate( $title, $args ) {
		$titleText = $title->getText();
		$frame = $this->parser->getPreprocessor()->newFrame();

		if (
			$frame->depth >= $this->parser->getOptions()->getMaxTemplateDepth()
		) {
			throw new MWException(
				"expandTemplate: template depth limit exceeded",
			);
		}

		if (
			MediaWikiServices::getInstance()
				->getNamespaceInfo()
				->isNonincludable( $title->getNamespace() )
		) {
			throw new MWException( "expandTemplate: template inclusion denied" );
		}

		[ $dom, $finalTitle ] = $this->parser->getTemplateDom( $title );
		if ( $dom === false ) {
			throw new MWException(
				"expandTemplate: template \"$titleText\" does not exist",
			);
		}

		if ( !$frame->loopCheck( $finalTitle ) ) {
			throw new MWException( "expandTemplate: template loop detected" );
		}

		$fargs = $this->parser->getPreprocessor()->newPartNodeArray( $args );
		$newFrame = $frame->newChild( $fargs, $finalTitle );
		// $text = $this->doCachedExpansion( $newFrame, $dom,
		// 	[
		// 		'frameId' => $frameId,
		// 		'frameId' => 'empty',
		// 		'template' => $finalTitle->getPrefixedDBkey(),
		// 		'args' => $fargs
		// 	]
		// );
		$text = $newFrame->expand( $dom );
		return $text;
	}
}
