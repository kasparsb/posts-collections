<?php

namespace PostsCollections;

class Plugin extends Base {

    public $allowed_post_types = [];

    private $collections = [];

    private $table = '';

    public function __construct() {
        parent::__construct();
        global $wpdb;

        $this->table = $wpdb->prefix.'wb_posts_collections';

        register_activation_hook($this->path.'index.php', [$this, 'install']);

        add_action('admin_init', [$this, 'admin_init']);

        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post', [$this, 'save_post']);
        
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'scripts_styles']);

        add_action('wp_ajax_postscollectionssaveorder', [$this, 'do_save_order']);
        add_action('wp_ajax_nopriv_postscollectionssaveorder', [$this, 'do_save_order']);

        add_action('wp_ajax_postscollectionsremove', [$this, 'do_remove']);
        add_action('wp_ajax_nopriv_postscollectionsremove', [$this, 'do_remove']);
    }

    public function install() {
        global $wpdb;

        $q = "CREATE TABLE $this->table (
            collection VARCHAR(100) NOT NULL,
            post_id INT(11) NOT NULL,
            `order` INT(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (collection, post_id)
            )";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta($q);
    }

    public function scripts_styles() {
        wp_register_style('postscollections-main', plugins_url( 'build/app.min-'.$this->package('version').'.css' , __FILE__ ));
        wp_register_script('postscollections-main', plugins_url( 'build/app.min-'.$this->package('version').'.js', __FILE__ ), ['jquery', 'jquery-ui-sortable']);

        wp_localize_script('postscollections-main', 'postscollections', ['ajax_url' => admin_url( 'admin-ajax.php' )]);
    }

    public function admin_init() {
        // Pārķeram posta dzēšanas eventu
        add_action('delete_post', [$this, 'delete_post'], 10);
        add_action('wp_trash_post', [$this, 'delete_post'], 10);
    }

    public function admin_menu() {
        add_menu_page(
            'Posts collections',
            'Posts collections',
            'publish_posts',
            'postscollections',
            [$this, 'page_postscollections']
        );
    }

    public function save_post($post_id) {
        // Check post type
        //if ($this->is_allowed_post_type()) {
            
            if (!$this->verify_nonce_metbox('postscollections')) {
                return $post_id;
            }
            
            // If this is an autosave, our form has not been submitted, so we don't want to do anything.
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return $post_id;
            }

            $collections = $this->get_collections();

            $post_collections = filter_input(INPUT_POST, 'postcollections', FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_REQUIRE_ARRAY]);
            $post_collections = is_array($post_collections) ? $post_collections : [];
            $post_collections = array_keys(array_filter($post_collections));

            
            $this->update_post_collections($post_id, $post_collections);
            
            foreach ($post_collections as $post_collection_id) {
                foreach ($collections as $collection) {
                    if ($collection['id'] == $post_collection_id) {
                        if ($collection['max_posts'] > 0) {
                            $this->check_collection_posts_limit($collection['id'], $collection['max_posts']);
                        }
                    }
                }
                
            }

            $this->update_post_meta_collections($post_id);
        //}
    }

    public function delete_post($id) {
        $this->remove_post($id);
    }

    public function add_metabox() {
        foreach ($this->get_allowed_post_types() as $post_type) {
            add_meta_box(
                'postcollection',
                __( 'Post collection', 'postscollections' ),
                [$this, 'metabox'],
                $post_type,
                'side'
            );
        }
    }

    public function metabox($post) {
        $this->nonce_field_metabox('postscollections');
        
        if (!is_string($c = get_post_meta($post->ID, '_postcollections', true))) {
            $c = '';
        }
        $collections = explode(',', $c);

        foreach ($this->get_collections($post->post_type) as $collection) {
            $id = $collection['id'];
            $caption = $collection['caption'];
            $post_type = $collection['post_types'];
            $this->collection_item_html($id, $caption, in_array($id, $collections));
        }
    }

    public function collection_item_html($id, $caption, $checked) {
        $checked_attr = $checked ? 'checked="checked"' : '';
        ?>
        <div>
            <input type="checkbox" id="chb_postcollections_<?php echo $id ?>" name="postcollections[<?php echo $id ?>]" <?php echo $checked_attr ?> />
            <label for="chb_postcollections_<?php echo $id ?>"><?php echo $caption ?></label>
        </div>
        <?php
    }

    public function page_postscollections() {
        $collections = $this->get_collections();

        $current_collection_id = filter_input(INPUT_GET, 'collection', FILTER_SANITIZE_STRING);
        $current_collection_id = $current_collection_id ? $current_collection_id : $collections[0]['id'];

        $stats = $this->get_collections_stats();

        wp_enqueue_style( 'postscollections-main' );
        wp_enqueue_script( 'postscollections-main' );
        ?>
        <div class="wrap">
            
            <div class="postscollections">
                <div class="icon32"><br></div><h2>Posts collections</h2>

                <div class="postscollections__collections">
                    <ul class="subsubsub">
                        <?php 
                        $first = true;
                        foreach ($collections as $collection):
                            $id = $collection['id'];
                            $caption = $collection['caption'];
                            $max_posts_html = $collection['max_posts'] > 0 ? $collection['max_posts'] : '&infin;';
                            ?>
                            <li>
                                <?php echo $first ? '' : '|' ?>
                                <a <?php echo $current_collection_id == $id ? 'class="current"' : '' ?> href="admin.php?page=postscollections&amp;collection=<?php echo $id ?>">
                                    <?php echo $caption ?>
                                    <?php if (array_key_exists($id, $stats)): ?>
                                        <span class="count">(<?php echo $stats[$id] ?> from <?php echo $max_posts_html ?>)</span>
                                    <?php endif ?>
                                </a>
                            </li>
                        <?php $first = false; endforeach ?>
                    </ul>
                </div>

                <ol class="postscollections__posts">
                    <?php foreach ($this->get_collection_posts($current_collection_id) as $post): ?>
                    <li>
                        <input type="hidden" name="post_id" value="<?php echo $post->ID ?>" />
                        <input type="hidden" name="collection" value="<?php echo $current_collection_id ?>" />
                        <a class="postscollections__remove">&times;</a>
                        <a class="postscollections__post"><?php echo $post->post_title ?></a>
                        <a class="postscollections__edit" href="<?php echo get_edit_post_link($post->ID) ?>" target="_blank">Edit</a>
                    </li>
                    <?php endforeach ?>
                </ol>
            </div>
        </div>
        <?php
    }

    public function get_allowed_post_types() {
        $r = apply_filters('wbpc_post_types', ['post']);
        return $r;
    }

    public function get_collections($post_type=null) {
        $collections = apply_filters('wbpc_collections', []);

        $r = [];
        if (is_null($post_type)) {
            $r = $collections;
        }
        else {
            foreach ($collections as $collection) {
                if (in_array($post_type, $collection['post_types'])) {
                    $r[] = $collection;
                }
            }
        }

        // Check fields
        foreach ($r as &$rr) {
            $rr['max_posts'] = array_key_exists('max_posts', $rr) ? $rr['max_posts'] : -1;
        }

        return $r;
    }

    /**
     * Atgriež postam piesaistītās kolekcijas ar order parametru
     */
    public function get_post_collection_rows($post_id) {
        global $wpdb;

        $q = $wpdb->prepare("
            SELECT collection, `order` 
            FROM $this->table
            WHERE post_id=%d
            ORDER BY `order` ASC
        ", $post_id);

        return $wpdb->get_results($q);
    }

    public function get_post_collections($post_id) {
        $r = [];
        $items = $this->get_post_collection_rows($post_id);
        foreach ($items as $item) {
            $r[] = $item->collection;
        }
        return $r;
    }

    public function get_post_collections_with_order($post_id) {
        $r = [];
        $items = $this->get_post_collection_rows($post_id);
        foreach ($items as $item) {
            $r[$item->collection] = $item->order;
        }
        return $r;
    }

    public function get_collection_posts($collection_id, $count=0) {
        global $wpdb;

        $this->clean_up_missing_posts();

        $limit = '';
        if ($count > 0) {
            $limit = 'limit '.$count;
        }

        $post_ids = $wpdb->get_col(
            $wpdb->prepare("
                select post_id 
                from $this->table
                where collection=%s
                order by `order` desc
                $limit
            ", $collection_id),
            0
        );

        $posts = [];

        if (count($post_ids) > 0) {
            $posts = get_posts([
                'post_type' => 'any',
                'include' => $post_ids,
                'orderby' => 'post__in'
            ]);
        }
        
        return $posts;
    }

    public function get_collections_stats() {
        global $wpdb;

        $q = "
            select w.collection, count(p.ID) AS cnt
            from $this->table w
            left join $wpdb->posts p ON p.ID=w.post_id
            where 
                p.ID is not null
                and p.post_status='publish'
            group by w.collection
        ";

        $r = [];
        foreach ($wpdb->get_results($q) as $row) {
            $r[$row->collection] = $row->cnt;
        }

        return $r;
    }

    public function get_by_collection($collection, $count) {
        return $this->get_collection_posts($collection, $count);
    }

    public function update_post_meta_collections($post_id) {
        update_post_meta($post_id, '_postcollections', implode(',', $this->get_post_collections($post_id)));
    }

    public function update_post_collections($post_id, $collections) {
        global $wpdb;

        $current = $this->get_post_collections_with_order($post_id);
        $new = [];
        
        // Merge esošās ar jaunajām. Saglabājam order esošajā kolekcijā
        foreach ($collections as $id) {
            // Saglabājam esošo order
            if (array_key_exists($id, $current)) {
                $order = $current[$id];
            }
            // Selektējam jauno order
            else {
                $q = $wpdb->prepare("
                    SELECT max(`order`)+1 FROM $this->table WHERE collection=%s
                ", $id);

                $order = intval($wpdb->get_var($q));
            }
            
            $new[] = [
                'collection' => $id,
                'order' => $order
            ];
        }

        // Remove current and insert new
        $d = $wpdb->prepare("
            DELETE FROM $this->table WHERE post_id=%d
        ", $post_id);

        $wpdb->query($d);

        foreach ($new as $w) {
            $w['post_id'] = $post_id;
            $wpdb->insert($this->table, $w);
        }
    }

    public function remove_post_from_collection($post_id, $collection_id) {
        global $wpdb;

        $wpdb->delete(
            $this->table,
            [
                'post_id' => $post_id, 
                'collection' => $collection_id
            ],
            [
                '%d', 
                '%s'
            ]
        );
    }

    public function remove_post($post_id) {
        global $wpdb;

        $wpdb->delete(
            $this->table,
            [
                'post_id' => $post_id
            ],
            [
                '%d'
            ]
        );
    }

    /**
     * Handlojam, lai kolekcijā nebūtu vairāk postu par $limit
     */
    public function check_collection_posts_limit($collection_id, $limit) {
        $posts = $this->get_collection_posts($collection_id);

        $posts_to_remove = array_slice($posts, $limit);

        foreach ($posts_to_remove as $post) {
            $this->remove_post_from_collection($post->ID, $collection_id);
            $this->update_post_meta_collections($post->ID);
        }
    }

    /**
     * Dzēšam postus, kuri vairs nav pieejami
     */
    public function clean_up_missing_posts() {
        global $wpdb;

        $q_post_types = implode(',', array_map(function($t) use($wpdb) {
            return $wpdb->prepare("%s", $t);
        }, $this->get_allowed_post_types()));

        $wpdb->query("
            delete from $this->table
            where (collection, post_id) IN (
                select collection, post_id 
                from (
                    select c.collection, c.post_id
                    from $this->table c
                    left join $wpdb->posts p 
                        on 
                            p.ID=c.post_id 
                            and post_status<>'trash'
                            and post_type in ($q_post_types)
                    where p.ID is null
                ) a
            )
        ");
    }

    /**
     * AJAX actions
     */
    public function do_save_order() {
        global $wpdb;
        
        $items = filter_input(INPUT_POST, 'order', FILTER_SANITIZE_STRING, ['flags' => FILTER_REQUIRE_ARRAY]);
        $items = array_reverse($items);

        $i = 1;
        foreach ($items as $item) {
            $wpdb->update( 
                $this->table, 
                [ 'order' => $i++ ],
                [ 
                    'post_id' => $item['post_id'],
                    'collection' => $item['collection']
                ],
                [ '%d' ],
                [ '%d', '%s' ]
            );

        }
        exit;
    }

    public function do_remove() {
        
        $item = filter_input(INPUT_POST, 'item', FILTER_SANITIZE_STRING, ['flags' => FILTER_REQUIRE_ARRAY]);

        if ($item['post_id'] > 0 && $item['collection'] != '') {
            $this->remove_post_from_collection($item['post_id'], $item['collection']);


            $collections = $this->get_collections();
            foreach ($collections as $collection) {
                if ($collection['id'] == $item['collection']) {
                    if ($collection['max_posts'] > 0) {
                        $this->check_collection_posts_limit($collection['id'], $collection['max_posts']);
                    }
                }
            }
                
            


            $this->update_post_meta_collections($item['post_id']);
        }
        
        exit;
    }
}