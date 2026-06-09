/**
 * Airy Wishlist Counter - Gutenberg Block (Server-Side Render)
 *
 * @package Airy_Wishlist
 */

(function (wp) {
	const { registerBlockType }                                 = wp.blocks;
	const { InspectorControls }                                 = wp.blockEditor || wp.editor;
	const { PanelBody, ToggleControl, SelectControl, Disabled } = wp.components;
	const { __ }               = wp.i18n;
	const { Fragment }         = wp.element;
	const { ServerSideRender } = wp.serverSideRender || wp.components;

	registerBlockType(
		'airy-wishlist/counter',
		{
			title: __( 'Wishlist Counter', 'airy-wishlist' ),
			description: __( 'Display wishlist counter with item count', 'airy-wishlist' ),
			icon: 'heart',
			category: 'widgets',
			keywords: [__( 'wishlist' ), __( 'heart' ), __( 'favorite' )],

			attributes: {
				showText: {
					type: 'boolean',
					default: false
				},
				icon: {
					type: 'string',
					default: 'heart'
				}
			},

			edit: function (props) {
				const { attributes, setAttributes } = props;
				const { showText, icon }            = attributes;

				return wp.element.createElement(
					Fragment,
					null,
					wp.element.createElement(
						InspectorControls,
						null,
						wp.element.createElement(
							PanelBody,
							{
								title: __( 'Counter Settings', 'airy-wishlist' ),
								initialOpen: true
							},
							wp.element.createElement(
								SelectControl,
								{
									label: __( 'Icon', 'airy-wishlist' ),
									value: icon,
									options: [
									{ label: __( 'Heart', 'airy-wishlist' ), value: 'heart' },
									{ label: __( 'Star', 'airy-wishlist' ), value: 'star' },
									{ label: __( 'Bookmark', 'airy-wishlist' ), value: 'bookmark' }
									],
									onChange: function (value) {
										setAttributes( { icon: value } );
									}
								}
							),
							wp.element.createElement(
								ToggleControl,
								{
									label: __( 'Show "Wishlist" Text', 'airy-wishlist' ),
									checked: showText,
									onChange: function (value) {
										setAttributes( { showText: value } );
									}
								}
							)
						)
					),
					wp.element.createElement(
						'div',
						{
							className: 'airy-wishlist-block-editor',
							style: {
								padding: '20px',
								background: '#f9f9f9',
								border: '1px dashed #ccc',
								borderRadius: '4px',
								textAlign: 'center'
							}
						},
						wp.element.createElement(
							'div',
							{
								style: {
									marginBottom: '10px',
									fontSize: '14px',
									color: '#666'
								}
							},
							wp.element.createElement( 'strong', null, __( 'Wishlist Counter', 'airy-wishlist' ) )
						),
						wp.element.createElement(
							'div',
							{
								style: {
									display: 'inline-flex',
									alignItems: 'center',
									gap: '8px',
									padding: '8px',
									background: '#fff',
									borderRadius: '4px',
									border: '1px solid #ddd'
								}
							},
							wp.element.createElement(
								'div',
								{
									style: {
										position: 'relative',
										display: 'inline-block'
									}
								},
								wp.element.createElement(
									'span',
									{
										style: {
											fontSize: '20px',
											lineHeight: '1'
										}
									},
									icon === 'heart' ? '♥' : (icon === 'star' ? '★' : '🔖')
								),
								wp.element.createElement(
									'span',
									{
										style: {
											position: 'absolute',
											top: '-8px',
											right: '-8px',
											background: '#cc1818',
											color: '#fff',
											borderRadius: '50%',
											minWidth: '16px',
											height: '16px',
											fontSize: '10px',
											display: 'flex',
											alignItems: 'center',
											justifyContent: 'center',
											fontWeight: 'bold',
											padding: '0 4px'
										}
									},
									'0'
								)
							),
							showText && wp.element.createElement(
								'span',
								{
									style: {
										fontSize: '14px'
									}
								},
								__( 'Wishlist', 'airy-wishlist' )
							)
						),
						wp.element.createElement(
							'p',
							{
								style: {
									marginTop: '10px',
									marginBottom: '0',
									fontSize: '12px',
									color: '#999'
								}
							},
							__( 'Settings: ', 'airy-wishlist' ),
							'Icon = ' + icon,
							', Show Text = ' + (showText ? 'Yes' : 'No')
						)
					)
				);
			},

			save: function () {
				return null;
			}
		}
	);

})( window.wp );