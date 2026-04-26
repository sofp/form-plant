/**
 * Form Plant block
 *
 * Lets the user pick a published Form Plant form and embeds its shortcode
 * into the post content. The block's save() returns the [fplant id="..."]
 * shortcode string so it lives in post_content like a hand-typed shortcode,
 * which keeps the existing front-end asset enqueue (has_shortcode-driven)
 * working exactly as before.
 *
 * Note: The icon SVG is mirrored from docs/assets/logo/formplant-logo-icon-32.svg.
 * If the master logo changes, update both files.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var registerBlockType = wp.blocks.registerBlockType;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;
	var blockEditor = wp.blockEditor || wp.editor;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var components = wp.components;
	var Placeholder = components.Placeholder;
	var SelectControl = components.SelectControl;
	var Spinner = components.Spinner;
	var PanelBody = components.PanelBody;
	var ExternalLink = components.ExternalLink;

	var blockIcon = el(
		'svg',
		{
			width: 24,
			height: 24,
			viewBox: '0 0 64 64',
			fill: 'none',
			xmlns: 'http://www.w3.org/2000/svg',
		},
		el( 'rect', { x: 12, y: 26, width: 40, height: 32, rx: 6, fill: '#2E5A3A' } ),
		el( 'rect', { x: 15, y: 29, width: 34, height: 26, rx: 4, fill: '#253d2d' } ),
		el( 'rect', { x: 19, y: 34, width: 26, height: 5, rx: 2.5, fill: '#3D7A4F', opacity: 0.5 } ),
		el( 'rect', { x: 19, y: 42, width: 26, height: 5, rx: 2.5, fill: '#3D7A4F', opacity: 0.5 } ),
		el( 'rect', { x: 19, y: 50, width: 12, height: 4, rx: 2, fill: '#5BB870' } ),
		el( 'path', {
			d: 'M32 26 C32 20 31 14 30 6',
			stroke: '#4A9960',
			strokeWidth: 3,
			strokeLinecap: 'round',
			fill: 'none',
		} ),
		el( 'path', { d: 'M29 14 C22 8 15 10 15 15 C15 20 21 21 27 17', fill: '#2E5A3A' } ),
		el( 'path', { d: 'M31 8 C37 2 44 4 44 9 C44 14 38 15 32 11', fill: '#5BB870' } ),
		el( 'circle', { cx: 29, cy: 4, r: 2.5, fill: '#A8E6B8' } )
	);

	function FormSelectEdit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var formId = attributes.formId || 0;

		var formsState = useState( null );
		var forms = formsState[ 0 ];
		var setForms = formsState[ 1 ];

		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];

		useEffect( function () {
			apiFetch( { path: '/form-plant/v1/forms' } )
				.then( function ( data ) {
					setForms( Array.isArray( data ) ? data : [] );
				} )
				.catch( function ( err ) {
					setError( ( err && err.message ) || __( 'Failed to load forms.', 'form-plant' ) );
					setForms( [] );
				} );
		}, [] );

		var blockProps = useBlockProps();

		var options = [
			{ value: '0', label: __( '— Select a form —', 'form-plant' ) },
		];
		var selectedTitle = '';
		if ( forms && forms.length > 0 ) {
			forms.forEach( function ( form ) {
				options.push( {
					value: String( form.id ),
					label: form.title || ( '#' + form.id ),
				} );
				if ( form.id === formId ) {
					selectedTitle = form.title || ( '#' + form.id );
				}
			} );
		}

		function onChangeForm( value ) {
			setAttributes( { formId: parseInt( value, 10 ) || 0 } );
		}

		var newFormUrl = ( window.fplantBlockData && window.fplantBlockData.newFormUrl )
			|| 'admin.php?page=fplant-form-new';

		var editFormUrl = '';
		if ( formId && window.fplantBlockData && window.fplantBlockData.newFormUrl ) {
			editFormUrl = window.fplantBlockData.newFormUrl + '&id=' + formId;
		}

		var inspector = el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Form settings', 'form-plant' ), initialOpen: true },
				el( SelectControl, {
					label: __( 'Form', 'form-plant' ),
					value: String( formId ),
					options: options,
					onChange: onChangeForm,
				} ),
				editFormUrl
					? el(
							ExternalLink,
							{ href: editFormUrl },
							__( 'Edit this form', 'form-plant' )
					  )
					: null
			)
		);

		// Loading state
		if ( forms === null ) {
			return el(
				'div',
				blockProps,
				el(
					Placeholder,
					{ icon: blockIcon, label: __( 'Form Plant', 'form-plant' ) },
					el( Spinner, null )
				)
			);
		}

		// No forms exist
		if ( forms.length === 0 && ! error ) {
			return el(
				'div',
				blockProps,
				el(
					Placeholder,
					{
						icon: blockIcon,
						label: __( 'Form Plant', 'form-plant' ),
						instructions: __( 'No forms have been created yet.', 'form-plant' ),
					},
					el(
						'a',
						{ href: newFormUrl, className: 'components-button is-primary' },
						__( 'Create a form', 'form-plant' )
					)
				)
			);
		}

		var instructions = error
			? error
			: ( formId
				? ( selectedTitle || __( 'Form selected.', 'form-plant' ) )
				: __( 'Select a form to embed.', 'form-plant' ) );

		return el(
			'div',
			blockProps,
			inspector,
			el(
				Placeholder,
				{
					icon: blockIcon,
					label: __( 'Form Plant', 'form-plant' ),
					instructions: instructions,
				},
				el( SelectControl, {
					label: __( 'Form', 'form-plant' ),
					hideLabelFromVision: true,
					value: String( formId ),
					options: options,
					onChange: onChangeForm,
				} )
			)
		);
	}

	registerBlockType( 'form-plant/form', {
		icon: blockIcon,
		edit: FormSelectEdit,
		save: function ( props ) {
			var formId = props.attributes.formId || 0;
			if ( ! formId ) {
				return null;
			}
			return el(
				'div',
				useBlockProps.save(),
				'[fplant id="' + formId + '"]'
			);
		},
	} );
} )( window.wp );
