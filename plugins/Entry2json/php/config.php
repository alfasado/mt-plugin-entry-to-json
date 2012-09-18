<?php
class Entry2json extends MTPlugin {

/*
    http://example.com/blog/index.html?to_json=1
    http://example.com/blog/category/index.html?to_json=1
    http://example.com/blog/2012/09/index.html?to_json=1
    http://example.com/blog/entry_permalink.html?to_json=1
*/

    var $registry = array(
        'name' => 'Entry2json',
        'id'   => 'Entry2json',
        'key'  => 'entry2json',
        'author_name' => 'Alfasado Inc.',
        'author_link' => 'http://alfasado.net/',
        'version' => '0.2',
        'callbacks' => array(
            'pre_build_page' => 'pre_build_page'
        ),
        'config_settings' => array(
            'Entry2jsonRequiresLogin' => array( 'default' => '' ), // comment
        ),
    );

    function pre_build_page ( $mt, $ctx, &$args ) {
        $app = $ctx->stash( 'bootstrapper' );
        if ( $app->param( 'to_json' ) ) {
            if ( $permission = $app->config( 'Entry2jsonRequiresLogin' ) ) {
                if (! $app->user() ) {
                    return;
                }
                if ( $permission != 1 ) {
                    if (! $app->can_do( $ctx, $permission ) ) {
                        return;
                    }
                }
            }
            $fileinfo = $app->stash( 'fileinfo' );
            $archive_type = $fileinfo->archive_type;
            $json = NULL;
            $blog = $ctx->stash( 'blog' );
            $objects = array();
            $objects[ 'fileinfo' ] = $fileinfo;
            if ( ( $archive_type == 'index' ) || ( $archive_type == 'Category' ) ||
                 ( $archive_type == 'Monthly' ) || ( $archive_type == 'Yearly' ) ) {
                $limit = $app->param( 'limit' );
                $offset = $app->param( 'offset' );
                $terms = array( 'blog_id' => $blog->id,
                                'status' => 2 );
                $extra = array();
                if ( $limit ) $extra[ 'limit' ] = $limit;
                if ( $offset ) $extra[ 'offset' ] = $offset;
                if ( $category = $ctx->stash( 'category' ) ) {
                    $category_id = $category->id;
                    $extra[ 'join' ] = array( 'mt_placement' => array( 'condition' =>
                    "entry_id=placement_entry_id AND placement_category_id={$category_id}" ) );
                    $category = $category->GetArray();
                    $objects[ 'category' ] = $category;
                }
                if ( $archive_type == 'Monthly' ) {
                    $startdate = $fileinfo->startdate;
                    $startdate = substr( $startdate, 0, 4 ) . '-' . substr( $startdate, 4, 2 ) . '%';
                    $terms[ 'authored_on' ] = array( 'like' => $startdate );
                }
                if ( $archive_type == 'Yearly' ) {
                    $startdate = $fileinfo->startdate;
                    $startdate = substr( $startdate, 0, 4 ) . '%';
                    $terms[ 'authored_on' ] = array( 'like' => $startdate );
                }
                $entries = $app->load( 'Entry', $terms, $extra );
                $entries_array = array();
                foreach ( $entries as $entry ) {
                    array_push( $entries_array, $this->_entry2json( $app, $ctx, $entry, TRUE ) );
                }
                $objects[ 'entries' ] = $entries_array;
                $json = json_encode( $objects );
            } elseif ( ( $archive_type == 'Individual' ) || ( $archive_type == 'Page' ) ) {
                $entry = $ctx->stash( 'entry' );
                if ( $entry ) {
                    $entry_obj = $this->_entry2json( $app, $ctx, $entry, TRUE );
                    $objects[ 'entry' ] = $entry_obj;
                }
                $json = json_encode( $objects );
            }
            if ( $json ) {
                $file = $app->stash( 'file' );
                $app->send_http_header( 'application/json; charset=UTF-8', filemtime( $file ), strlen( $json ) );
                echo $json;
                exit();
            }
        }
    }

    function _entry2json ( $app, $ctx, $entry, $wantarray = NULL ) {
        $get_fields = $app->load( 'Field', array(
                        'obj_type' => $entry->class,
                        'blog_id' => array( 0, $entry->blog_id ) ) );
        $get_categories = $entry->categories();
        $get_tags = $app->fetch_tags( $entry );
        $json = $entry->GetArray();
        if ( $get_fields ) {
            foreach ( $get_fields as $field ) {
                $column_name = 'field.' . $field->basename;
                $field_val = $entry->{ $entry->_prefix . $column_name };
                $json[ $column_name ] = $field_val;
            }
        }
        if ( $get_categories ) {
            $categories = array();
            foreach ( $get_categories as $category ) {
                $category = $category->GetArray();
                array_push( $categories, $category );
            }
            $json[ 'categories' ] = $categories;
        }
        if ( $get_tags ) {
            $tags = array();
            foreach ( $get_tags as $tag ) {
                $tag = $tag->GetArray();
                array_push( $tags, $tag );
            }
            $json[ 'tags' ] = $tags;
        }
        if (! $wantarray ) {
            $json = json_encode( $json );
        }
        return $json;
    }
}

?>