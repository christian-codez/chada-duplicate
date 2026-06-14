/**
 * Chada Duplicate — block-editor "Copy to a new draft" control.
 *
 * Plain JS against the `wp.*` globals (no build step, matching the sibling
 * plugins). Renders a control in the Status & visibility panel
 * (PluginPostStatusInfo) and an item in the editor's ⋯ menu (PluginMoreMenuItem).
 * Clicking either shows a brief "Duplicating…" state, then navigates to the
 * server handler, which clones the post and redirects to the new draft.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! window.cdupEditor || ! window.cdupEditor.duplicateUrl ) {
		return;
	}

	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var registerPlugin = wp.plugins.registerPlugin;
	var __ = wp.i18n.__;

	// PluginPostStatusInfo / PluginMoreMenuItem moved from @wordpress/edit-post
	// to @wordpress/editor in WP 6.6; prefer the new home to avoid a deprecation
	// notice, falling back to wp.editPost on 6.0–6.5.
	var slots = wp.editor && wp.editor.PluginPostStatusInfo ? wp.editor : wp.editPost;
	var PluginPostStatusInfo = slots && slots.PluginPostStatusInfo;
	var PluginMoreMenuItem = slots && slots.PluginMoreMenuItem;

	if ( ! PluginPostStatusInfo && ! PluginMoreMenuItem ) {
		return;
	}

	var Button = wp.components.Button;
	var MenuItem = wp.components.MenuItem;
	var Dashicon = wp.components.Dashicon;

	var IDLE_LABEL = __( 'Copy to a new draft', 'chada-duplicate' );
	var BUSY_LABEL = __( 'Duplicating…', 'chada-duplicate' );

	function ChadaDuplicateControls() {
		var duplicatingState = useState( false );
		var isDuplicating = duplicatingState[ 0 ];
		var setIsDuplicating = duplicatingState[ 1 ];

		function startDuplicate() {
			if ( isDuplicating ) {
				return;
			}
			setIsDuplicating( true );
			window.location.href = window.cdupEditor.duplicateUrl;
		}

		var label = isDuplicating ? BUSY_LABEL : IDLE_LABEL;

		var statusInfoControl = PluginPostStatusInfo
			? createElement(
					PluginPostStatusInfo,
					{ className: 'cdup-copy-to-draft' },
					createElement(
						Button,
						{
							className: 'cdup-copy-to-draft-button',
							isBusy: isDuplicating,
							'aria-disabled': isDuplicating,
							onClick: startDuplicate,
						},
						createElement( Dashicon, { icon: 'admin-page' } ),
						' ',
						label
					)
			  )
			: null;

		var moreMenuControl = PluginMoreMenuItem
			? createElement(
					PluginMoreMenuItem,
					{ icon: 'admin-page', onClick: startDuplicate },
					label
			  )
			: null;

		return createElement( Fragment, null, statusInfoControl, moreMenuControl );
	}

	registerPlugin( 'chada-duplicate-editor', {
		render: ChadaDuplicateControls,
	} );
} )( window.wp );
