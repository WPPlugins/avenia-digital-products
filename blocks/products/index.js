/**
 * Products block editor script.
 *
 * @package AveniaDigitalProducts
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const {
			perPage,
			category,
			tag,
			orderBy,
			order,
			columns,
			showImage,
			showExcerpt,
			showPrice,
		} = attributes;

		const categories = useSelect(
			( select ) =>
				select( 'core' ).getEntityRecords( 'taxonomy', 'aveniadp_product_category', {
					per_page: -1,
				} ) || [],
			[]
		);

		const tags = useSelect(
			( select ) =>
				select( 'core' ).getEntityRecords( 'taxonomy', 'aveniadp_product_tag', {
					per_page: -1,
				} ) || [],
			[]
		);

		const categoryOptions = [
			{
				label: __( 'All Categories', 'avenia-digital-products' ),
				value: '',
			},
			...categories.map( ( term ) => ( {
				label: term.name,
				value: term.slug,
			} ) ),
		];

		const tagOptions = [
			{
				label: __( 'All Tags', 'avenia-digital-products' ),
				value: '',
			},
			...tags.map( ( term ) => ( {
				label: term.name,
				value: term.slug,
			} ) ),
		];

		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody
						title={ __( 'Product Settings', 'avenia-digital-products' ) }
					>
						<RangeControl
							label={ __( 'Products per page', 'avenia-digital-products' ) }
							value={ perPage || 12 }
							onChange={ ( value ) =>
								setAttributes( { perPage: value } )
							}
							min={ 1 }
							max={ 50 }
						/>
						<RangeControl
							label={ __( 'Columns', 'avenia-digital-products' ) }
							value={ columns }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							min={ 1 }
							max={ 6 }
						/>
						<SelectControl
							label={ __( 'Category', 'avenia-digital-products' ) }
							value={ category }
							options={ categoryOptions }
							onChange={ ( value ) =>
								setAttributes( { category: value } )
							}
						/>
						<SelectControl
							label={ __( 'Tag', 'avenia-digital-products' ) }
							value={ tag }
							options={ tagOptions }
							onChange={ ( value ) => setAttributes( { tag: value } ) }
						/>
						<SelectControl
							label={ __( 'Order by', 'avenia-digital-products' ) }
							value={ orderBy }
							options={ [
								{
									label: __( 'Date', 'avenia-digital-products' ),
									value: 'date',
								},
								{
									label: __( 'Title', 'avenia-digital-products' ),
									value: 'title',
								},
								{
									label: __( 'Price', 'avenia-digital-products' ),
									value: 'meta_value_num',
								},
								{
									label: __( 'Menu Order', 'avenia-digital-products' ),
									value: 'menu_order',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { orderBy: value } )
							}
						/>
						<SelectControl
							label={ __( 'Order', 'avenia-digital-products' ) }
							value={ order }
							options={ [
								{
									label: __( 'Descending', 'avenia-digital-products' ),
									value: 'DESC',
								},
								{
									label: __( 'Ascending', 'avenia-digital-products' ),
									value: 'ASC',
								},
							] }
							onChange={ ( value ) => setAttributes( { order: value } ) }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Display Options', 'avenia-digital-products' ) }
					>
						<ToggleControl
							label={ __( 'Show image', 'avenia-digital-products' ) }
							checked={ showImage }
							onChange={ ( value ) =>
								setAttributes( { showImage: value } )
							}
						/>
						<ToggleControl
							label={ __( 'Show excerpt', 'avenia-digital-products' ) }
							checked={ showExcerpt }
							onChange={ ( value ) =>
								setAttributes( { showExcerpt: value } )
							}
						/>
						<ToggleControl
							label={ __( 'Show price', 'avenia-digital-products' ) }
							checked={ showPrice }
							onChange={ ( value ) =>
								setAttributes( { showPrice: value } )
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
