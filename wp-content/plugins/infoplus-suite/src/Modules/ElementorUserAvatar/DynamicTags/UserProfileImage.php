<?php
namespace Infoplus\Suite\Modules\ElementorUserAvatar\DynamicTags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) exit;

class UserProfileImage extends Data_Tag
{
    /**
     * Get tag name
     */
    public function get_name()
    {
        return 'infoplus-user-profile-image';
    }

    /**
     * Get tag title
     */
    public function get_title()
    {
        return __('Infoplus User Profile Image', 'infoplus-suite');
    }

    /**
     * Get tag group
     */
    public function get_group()
    {
        return 'author';
    }

    /**
     * Get tag categories
     */
    public function get_categories()
    {
        return [
            \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY,
        ];
    }

    /**
     * Register controls
     */
    protected function register_controls()
    {
        $this->add_control(
            'fallback_image',
            [
                'label' => __('Fallback Image', 'infoplus-suite'),
                'type' => Controls_Manager::MEDIA,
                'description' => __('Image to show if user has no profile picture', 'infoplus-suite'),
            ]
        );

        $this->add_control(
            'user_source',
            [
                'label' => __('User Source', 'infoplus-suite'),
                'type' => Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current' => __('Current User', 'infoplus-suite'),
                    'author' => __('Post Author', 'infoplus-suite'),
                    'custom' => __('Custom User ID', 'infoplus-suite'),
                ],
            ]
        );

        $this->add_control(
            'custom_user_id',
            [
                'label' => __('User ID', 'infoplus-suite'),
                'type' => Controls_Manager::NUMBER,
                'condition' => [
                    'user_source' => 'custom',
                ],
            ]
        );
    }

    /**
     * Get user ID based on settings
     */
    private function get_user_id()
    {
        $settings = $this->get_settings();
        $user_source = $settings['user_source'] ?? 'current';

        switch ($user_source) {
            case 'current':
                return get_current_user_id();
            
            case 'author':
                return get_the_author_meta('ID');
            
            case 'custom':
                return (int) ($settings['custom_user_id'] ?? 0);
            
            default:
                return get_current_user_id();
        }
    }

    /**
     * Get user profile image URL
     */
    private function get_user_profile_image_url($user_id)
    {
        if (!$user_id) {
            return '';
        }

        // Try different meta keys that common user profile picture plugins use
        $possible_meta_keys = [
            'user_profile_picture',           // User Profile Picture plugin
            'wp_user_avatar',                 // WP User Avatar plugin  
            'basic_user_avatar',              // Simple Local Avatars
            'simple_local_avatar',            // Simple Local Avatars
            'infoplus_user_avatar',           // Our custom field
        ];

        foreach ($possible_meta_keys as $meta_key) {
            $custom_avatar = get_user_meta($user_id, $meta_key, true);
            
            if (!empty($custom_avatar)) {
                // If it's an attachment ID, get the URL
                if (is_numeric($custom_avatar)) {
                    $image_url = wp_get_attachment_url($custom_avatar);
                    if ($image_url) {
                        return $image_url;
                    }
                }
                
                // If it's already a URL
                if (filter_var($custom_avatar, FILTER_VALIDATE_URL)) {
                    return $custom_avatar;
                }
                
                // If it's an array with URL
                if (is_array($custom_avatar)) {
                    if (isset($custom_avatar['url'])) {
                        return $custom_avatar['url'];
                    }
                    if (isset($custom_avatar['full'])) {
                        return $custom_avatar['full'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * Get the value (returns image data)
     */
    public function get_value(array $options = [])
    {
        $user_id = $this->get_user_id();
        $image_url = $this->get_user_profile_image_url($user_id);

        // If no image found, use fallback
        if (empty($image_url)) {
            $settings = $this->get_settings();
            $fallback = $settings['fallback_image'] ?? [];
            
            if (!empty($fallback['url'])) {
                $image_url = $fallback['url'];
            }
        }

        // Return image data in Elementor format
        return [
            'id' => '',
            'url' => $image_url,
        ];
    }
}