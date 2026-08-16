/**
 * Cart Icon block editor script.
 *
 * @package AveniaDigitalProducts
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const {
			iconStyle,
			iconSize,
			showCount,
			hideCountWhenEmpty,
			label,
		} = attributes;

		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Cart Icon', 'avenia-digital-products' ) }>
						<SelectControl
							label={ __( 'Icon style', 'avenia-digital-products' ) }
							value={ iconStyle || 'bag' }
							options={ [
								{
									label: __( 'Shopping bag', 'avenia-digital-products' ),
									value: 'bag',
								},
								{
									label: __( 'Shopping cart', 'avenia-digital-products' ),
									value: 'cart',
								},
								{
									label: __( 'Basket', 'avenia-digital-products' ),
									value: 'basket',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { iconStyle: value } )
							}
						/>
						<RangeControl
							label={ __( 'Icon size (px)', 'avenia-digital-products' ) }
							value={ iconSize || 24 }
							onChange={ ( value ) =>
								setAttributes( { iconSize: value } )
							}
							min={ 16 }
							max={ 48 }
						/>
						<ToggleControl
							label={ __( 'Show item count', 'avenia-digital-products' ) }
							checked={ showCount }
							onChange={ ( value ) =>
								setAttributes( { showCount: value } )
							}
						/>
						{ showCount && (
							<ToggleControl
								label={ __(
									'Hide count when cart is empty',
									'avenia-digital-products'
								) }
								checked={ hideCountWhenEmpty }
								onChange={ ( value ) =>
									setAttributes( { hideCountWhenEmpty: value } )
								}
							/>
						) }
						<TextControl
							label={ __( 'Accessible label', 'avenia-digital-products' ) }
							help={ __(
								'Used by screen readers. Defaults to “Cart”.',
								'avenia-digital-products'
							) }
							value={ label }
							onChange={ ( value ) =>
								setAttributes( { label: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		);
	},
	save: () => null,
} );
