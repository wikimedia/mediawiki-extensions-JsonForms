<?php

namespace MediaWiki\Extension\JsonForms\Utils;

/**
 * Build a tree from a flat list of slash-separated paths, and render it as nested <ul>/<li>.
 *
 * Example:
 *   $paths = [
 *       'Fiction',
 *       'Non-fiction/Science',
 *       'Non-fiction/Science/Physics',
 *       'Non-fiction/Science/Chemistry',
 *       'Non-fiction/Science/Biology',
 *       'Non-fiction/Science/Mathematics',
 *       'Non-fiction/Science/Astronomy',
 *   ];
 *   echo TreeBuilder::renderHtml( $paths, '/' );
 *
 * Produces:
 *   <ul class="tree">
 *     <li class="tree-leaf">Fiction</li>
 *     <li class="tree-folder">Non-fiction
 *       <ul class="tree">
 *         <li class="tree-folder">Science
 *           <ul class="tree">
 *             <li class="tree-leaf">Physics</li>
 *             ...
 *           </ul>
 *         </li>
 *       </ul>
 *     </li>
 *   </ul>
 */
class TreeBuilder {

	/**
	 * Convert a flat list of slash-separated paths into a tree of nodes.
	 *
	 * @param string[] $paths e.g. [ 'Fiction', 'Non-fiction/Science/Physics' ]
	 * @param string $sep Path separator (default '/')
	 * @param array $options Extra keys applied to leaf nodes only
	 * 			(e.g. [ 'tooltip' => 'category' ])
	 * @return array List of node arrays
	 */
	public static function pathsToTree( array $paths, string $sep = '/', array $options = [] ): array {
		$tree = [];

		foreach ( $paths as $path ) {
			$tree = self::insertPath( $tree, explode( $sep, $path ), $options );
		}

		return $tree;
	}

	/**
	 * Insert a path into the tree, returning the new tree.
	 *
	 * @param array $tree
	 * @param array $parts Path segments (mutated via array_shift)
	 * @param array $options
	 * @return array
	 */
	private static function insertPath( array $tree, array $parts, array $options ): array {
		$title = array_shift( $parts );
		$isLeaf = count( $parts ) === 0;

		// Find existing sibling by title.
		$idx = null;
		foreach ( $tree as $i => $node ) {
			if ( $node['title'] === $title ) {
				$idx = $i;
				break;
			}
		}

		if ( $idx === null ) {
			// Create a new node.
			$node = [ 'title' => $title ];

			if ( $isLeaf ) {
				foreach ( $options as $k => $v ) {
					$node[ $k ] = $v;
				}
			} else {
				$node['folder'] = true;
				$node['children'] = self::insertPath( [], $parts, $options );
			}

			$tree[] = $node;
			return $tree;
		}

		// Sibling exists: update in place.
		if ( $isLeaf ) {
			// Don't downgrade a folder to a leaf.
			if ( empty( $tree[ $idx ]['folder'] ) ) {
				foreach ( $options as $k => $v ) {
					if ( !isset( $tree[ $idx ][ $k ] ) ) {
						$tree[ $idx ][ $k ] = $v;
					}
				}
			}
		} else {
			$tree[ $idx ]['folder'] = true;
			if ( !isset( $tree[ $idx ]['children'] ) ) {
				$tree[ $idx ]['children'] = [];
			}
			$tree[ $idx ]['children'] = self::insertPath(
				$tree[ $idx ]['children'],
				$parts,
				$options
			);
		}

		return $tree;
	}

	/**
	 * Render a flat list of paths as nested <ul>/<li>.
	 *
	 * @param string[] $paths
	 * @param string $sep
	 * @param array $attributes Extra attributes on the outer <ul> (e.g. [ 'class' => 'my-tree' ])
	 * @param array $options Extra keys applied to leaves in the tree
	 * @return string
	 */
	public static function renderHtml(
		array $paths,
		string $sep = '/',
		array $attributes = [],
		array $options = []
	): string {
		$tree = self::pathsToTree( $paths, $sep, $options );
		return self::renderList( $tree, $attributes );
	}

	/**
	 * Render a list of nodes as a <ul>, recursing into children.
	 *
	 * @param array $nodes
	 * @param array $attributes Applied only to the outermost <ul>
	 * @param int $depth Internal — tracks nesting for the outer check
	 * @return string
	 */
	private static function renderList( array $nodes, array $attributes = [], int $depth = 0 ): string {
		if ( $nodes === [] ) {
			return '';
		}

		$attr = '';
		if ( $depth === 0 ) {
			$attrs = $attributes + [ 'class' => 'tree' ];
			foreach ( $attrs as $k => $v ) {
				$attr .= ' ' . htmlspecialchars( $k, ENT_QUOTES )
					. '="' . htmlspecialchars( (string)$v, ENT_QUOTES ) . '"';
			}
		}

		$html = "<ul{$attr}>\n";

		foreach ( $nodes as $node ) {
			$html .= self::renderNode( $node, $depth + 1 );
		}

		$html .= "</ul>\n";
		return $html;
	}

	/**
	 * Render a single node as an <li>, recursing if it has children.
	 */
	private static function renderNode( array $node, int $depth ): string {
		$isFolder = !empty( $node['folder'] ) && !empty( $node['children'] );
		$class = $isFolder ? 'tree-folder' : 'tree-leaf';

		// Title may contain HTML (e.g. links) — treat it as raw if a flag is set,
		// otherwise escape it. Default: escape.
		$title = empty( $node['rawTitle'] )
			? htmlspecialchars( $node['title'], ENT_QUOTES )
			: $node['title'];

		$html = str_repeat( "\t", $depth )
			. "<li class=\"{$class}\">{$title}";

		if ( $isFolder ) {
			$html .= "\n" . self::renderList( $node['children'], [], $depth );
		}

		$html .= "</li>\n";
		return $html;
	}
}
