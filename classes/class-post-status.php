<?php

/*
Post Statuses for the Argentum Theme
*/
namespace ArgentumPostStatus\Classes;
require_once get_template_directory() . '/inc/post-status-core-fix.php';

class PostStatus {
   
  public function __construct($statuses) {
    add_action( 'init',  array( $this, 'argentum_generate_post_status'), 0 );


      if ( is_admin() ) {

         
         add_action( 'post_submitbox_misc_actions', array( $this, 'argentum_add_post_status_to_dropdown' ), 10);
         add_action( 'admin_enqueue_scripts', array( $this, 'argentum_admin_load_post_status_scripts' ) );
         add_action( 'transition_post_status', array( $this, 'argentum_notify_editors_by_email' ), 10, 3 );
         add_action( 'restrict_manage_posts', array($this,'argentum_filter_posts_by_post_status'),10,2 );
         add_action( 'manage_posts_columns', array($this,'argentum_add_post_status_column'));
         add_action( 'manage_posts_custom_column', array($this,'argentum_post_status_custom_column'),10,2);
         add_action( 'manage_pages_columns', array($this,'argentum_add_post_status_column'));
         add_action( 'manage_pages_custom_column', array($this,'argentum_post_status_custom_column'),10,2);
         add_action('admin_footer-edit.php', array( $this, 'argentum_add_post_status_to_quickedit' ),10);

        }

      $this->statuses = $statuses;
      $this->status_names = array();
      $this->PostStatusCoreFix = new ArgentumPostStatusCoreFix($statuses);
   }
   public function argentum_admin_load_post_status_scripts($hook)
   {
      if ( 'post.php' == $hook ) {
         wp_enqueue_script( 'argentum-post-status', get_template_directory_uri() . '/admin/assets/js/post-status.js',array('argentum-post-edit'), false, 1);
      }
   }

  
   public function argentum_generate_post_status() {
      foreach ($this->statuses as $status )
      {
         $status_slug = $status['slug'];
         $status_singular = $status['singular'];
         $status_plural = $status['plural'];
   
         $args = array(
            'label'                     => _x( $status_singular, 'Status General Name', 'argentum-theme' ),
            'label_count'               => _n_noop( $status_singular.' (%s)',  $status_plural.' (%s)', 'argentum-theme' ), 
            'protected'                 => true,
            '_builtin'                  => false,
            
         );
         register_post_status( $status_slug, $args );
         $this->status_names[$status_slug] = $status_singular;
         
      }
  }

   public function argentum_add_post_status_to_dropdown($post)
   {
      ?>
      <script>
      jQuery(document).ready(function($){
        
      <?php
         foreach ($this->statuses as $status )
         {
            $slug = $status['slug'];
            $name = $status['singular'];
            ?>
            $("select#post_status").append("<option value=\"<?php echo $slug; ?>\" <?php selected($slug, $post->post_status);?> ><?php echo $name; ?> </option>");
         <?php   
         } ?>
         <?php
         foreach ($this->statuses as $status )
         {
            if ($status['slug'] == $post->post_status)
            {
               $name = $status['singular'];
               break;
            }
         }
            
         ?>
  
      }); 
      </script>
      <?php
   }

    public function argentum_add_post_status_to_quickedit()
   {
      ?>
      <script>
      jQuery(document).ready(function($){
      $.each($(('select[name="_status"]')), function(quickedit) {
        <?php
         foreach ($this->statuses as $status )
         {
            $slug = $status['slug'];
            $name = $status['singular'];
            ?>
            $(this).append("<option value=\"<?php echo $slug; ?>\" ><?php echo $name; ?> </option>");
         <?php   
         } ?>
         
  
      }); 
    });
      </script>
      <?php
   }
   
   public function argentum_notify_editors_by_email($new_status, $old_status, $post)
   {
      if ($new_status == $old_status)
      {
         return;
      }
      $editors = ['sohini', 'sinndhuja', 'admin'];
      if ($new_status == 'ready-for-subbing' || $new_status == 'publish' ) 
      {
         // Notify authors and editors
         $coauthors =  get_coauthors( $post->ID );
         $authoremails = array();
         $authornames = array();
         $editoremails = array();
         $editornames = array();
         $i = 0;
         foreach ($coauthors as $coauthor) 
         {
            $authorid = $coauthor->ID;
            $authoremails[$i] = get_the_author_meta('user_email',$authorid);
            $authornames[$i] = get_the_author_meta('display_name',$authorid);
            $i++;
         }
         
         $i = 0;
         foreach ($editors as $editor)
         {
            $user = get_user_by('slug',$editor);
            $authorid = $user->ID;
            $editoremails[$i] = get_the_author_meta('user_email',$authorid);
            $editornames[$i] = get_the_author_meta('display_name',$authorid);
            $i++;
         }
         $site_title = '[ '.get_bloginfo( 'name' ).' ] ';

         $statuses = array_merge(get_post_statuses(),$this->status_names);

         if ($new_status == 'publish')
         {
            $subject = $site_title . 'Article Published: ' . ' "' . $post->post_title. '"';
            $body = '<p>Post Titled'. $post->post_title . 'ID: ' . $post->ID . 'was published by '. get_the_modified_author() . 'on '. get_post_time('U',false, $post).". </p>";
         }
         else if ($new_status == 'ready-for-subbing')
         {
            $subject = $site_title . 'Article Ready For Subbing: ' . ' "' . $post->post_title. '"';
            $body = '<p>Post Titled '. $post->post_title . ' ID: ' . $post->ID . ' is ready to be sub-edited. It was saved at '. get_post_time('l, F j, Y',false, $post).". </p>";
         }

         $body .= "<p> {$statuses[$old_status]} => {$statuses[$new_status]} </p>";
         $body .= '<p> <strong>Post Details</strong> </p>';
         $body .= '<strong><em>Authors</em></strong> <br>';
         foreach ($authornames as $authorname) 
         {
            $body .= $authorname.'<br>';
         }
         $body .= '<p>Edit Link: <a href = "' . get_edit_post_link($post->ID). '">'.get_edit_post_link($post->ID).'</a></p>';
         $body .= '<p>View Link: <a href = "' . get_permalink($post->ID). '">'.get_permalink($post->ID).'</a></p>';
        
         $to = array_merge($authoremails, $editoremails);
         
         add_filter( 'wp_mail_content_type', array($this,'argentum_set_mail_content_type_html') );
         wp_mail($to, $subject, $body);
         remove_filter( 'wp_mail_content_type', array($this,'argentum_set_mail_content_type_html') );
     }
   }

   public function argentum_filter_posts_by_post_status($post_type, $which)
   {
      
      
      $names = get_post_statuses();
      $valid_statuses = array('draft','publish', );
      foreach ($valid_statuses as $valid)
      {
         $post_status_names[$valid] = $names[$valid];

      }
      $post_status_names = array_merge($post_status_names,$this->status_names);

      $val = 'all';
      if (isset($_REQUEST['post_status'])&& $_REQUEST['post_status'] != "")
      {
         $val = $_REQUEST['post_status'];
      }
      echo '<select name="post_status" id="post_status" >';
      echo '<option value="all"'. selected('all',$val,false) .'>' .__('All Statuses','argentum-theme'). ' </option>';
      foreach ($post_status_names as $value => $label)
      {
         
        echo '<option value="'.$value.'"'. selected($value,$val) .'>'. __($label,'argentum-theme'). ' </option>';
      }   
      echo '</select>';
   }

   public function argentum_add_post_status_column($columns) {
      unset($columns['tags']);
      unset($columns['comments']);
      unset($columns['taxonomy-language']);
      unset($columns['taxonomy-profile']);
      unset($columns['taxonomy-movie']);

      return array_merge( $columns, 
                array('post_status' => __('Post Status')) );
  }

  public function argentum_post_status_custom_column($column, $post_id)
  {
     switch($column)
     {
         case 'post_status':
            $statuses = array_merge(get_post_statuses(),$this->status_names);
            $status = get_post_status($post_id);
            _e($statuses[$status],'argentum-theme');
            break;
     }
  }

   public function argentum_set_mail_content_type_html()
   {
      return 'text/html';
   }

}
   
$statuses = array (
   array('slug' => 'pitch', 'singular' => 'Pitch', 'plural' => 'Pitches'),
   array('slug' => 'assigned', 'singular' => 'Assigned', 'plural' => 'Assigned Stories'),
   array('slug' => 'ready-for-subbing', 'singular' => 'Ready to Sub', 'plural' => 'Ready To Sub Posts'),
   array('slug' => 'ready-to-publish', 'singular' => 'Ready To Publish', 'plural' => 'Ready To Publish Stories'),
   array('slug' => 'interview-scheduled', 'singular' => 'Interview Scheduled', 'plural' => 'Interviews Scheduled'),
   array('slug' => 'no-publish', 'singular' => 'No Publish', 'plural' => 'No Publish Posts')
);

global $custom_post_statuses;
$custom_post_statuses = new ArgentumPostStatuses($statuses);





