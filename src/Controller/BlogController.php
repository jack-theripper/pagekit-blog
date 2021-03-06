<?php

namespace Pagekit\Blog\Controller;

use Pagekit\Application as App;
use Pagekit\Blog\Model\Category;
use Pagekit\Blog\Model\Comment;
use Pagekit\Blog\Model\Post;
use Pagekit\User\Model\Role;

/**
 * @Access(admin=true)
 */
class BlogController
{
    /**
     * @Access("blog: manage own posts || blog: manage all posts")
     * @Request({"filter": "array", "page":"int"})
     */
    public function postAction($filter = null, $page = null)
    {
        return [
            '$view' => [
                'title' => __('Posts'),
                'name'  => 'blog/admin/post-index.php'
            ],
            '$data' => [
                'statuses' => Post::getStatuses(),
                'authors'  => Post::getAuthors(),
                'canEditAll' => App::user()->hasAccess('blog: manage all posts'),
                'config'   => [
                    'filter' => (object) $filter,
                    'page'   => (int) $page
                ]
            ]
        ];
    }

    /**
     * @Route("/post/edit", name="post/edit")
     * @Access("blog: manage own posts || blog: manage all posts")
     * @Request({"id": "int"})
     */
    public function editAction($id = 0)
    {
        try {

            if (!$post = Post::where(compact('id'))->related('user', 'categories')->first()) {

                if ($id) {
                    App::abort(404, __('Invalid post id.'));
                }

                $module = App::module('blog');

                $post = Post::create([
                    'user_id' => App::user()->id,
                    'status' => Post::STATUS_DRAFT,
                    'date' => new \DateTime(),
                    'comment_status' => (bool) $module->config('posts.comments_enabled')
                ]);

                $post->set('title', $module->config('posts.show_title'));
                $post->set('markdown', $module->config('posts.markdown_enabled'));
            }

            $user = App::user();
            if(!$user->hasAccess('blog: manage all posts') && $post->user_id !== $user->id) {
                App::abort(403, __('Insufficient User Rights.'));
            }

            $roles = App::db()->createQueryBuilder()
                ->from('@system_role')
                ->where(['id' => Role::ROLE_ADMINISTRATOR])
                ->whereInSet('permissions', ['blog: manage all posts', 'blog: manage own posts'], false, 'OR')
                ->execute('id')
                ->fetchAll(\PDO::FETCH_COLUMN);

            $authors = App::db()->createQueryBuilder()
                ->from('@system_user')
                ->whereInSet('roles', $roles)
                ->execute('id, username')
                ->fetchAll();

            return [
                '$view' => [
                    'title' => $id ? __('Edit Post') : __('Add Post'),
                    'name'  => 'blog/admin/post-edit.php'
                ],
                '$data' => [
                    'post'     => $post,
                    'statuses' => Post::getStatuses(),
                    'roles'    => array_values(Role::findAll()),
                    'canEditAll' => $user->hasAccess('blog: manage all posts'),
                    'authors'  => $authors,
                    'categories' => Category::findAll()
                ],
                'post' => $post
            ];

        } catch (\Exception $e) {

            App::message()->error($e->getMessage());

            return App::redirect('@blog/post');
        }
    }

    /**
     * @Access("blog: manage comments")
     * @Request({"filter": "array", "post":"int", "page":"int"})
     */
    public function commentAction($filter = [], $post = 0, $page = null)
    {
        $post = Post::find($post);
        $filter['order'] = 'created DESC';

        return [
            '$view' => [
                'title' => $post ? __('Comments on %title%', ['%title%' => $post->title]) : __('Comments'),
                'name'  => 'blog/admin/comment-index.php'
            ],
            '$data'   => [
                'statuses' => Comment::getStatuses(),
                'config'   => [
                    'filter' => (object) $filter,
                    'page'   => $page,
                    'post'   => $post,
                    'limit'  => App::module('blog')->config('comments.comments_per_page')
                ]
            ]
        ];
    }

    /**
     * @Access("system: access settings")
     */
    public function settingsAction()
    {
        return [
            '$view' => [
                'title' => __('Blog Settings'),
                'name'  => 'blog/admin/settings.php'
            ],
            '$data' => [
                'config' => App::module('blog')->config()
            ]
        ];
    }

    /**
     * The list of categories in admin area.
     *
     * @param array $filter
     * @param int $page
     *
     * @Route("/categories", name="admin/categories")
     * @Request({"filter": "array", "page":"int"})
     * @Access("blog: manage all posts")
     *
     * @return array
     */
    public function categoriesAction($filter = null, $page = null)
    {
        return [
            '$view' => [
                'title' => __('Categories'),
                'name' => 'blog/admin/categories.php'
            ],
            '$data' => [
                'config' => [
                    'filter' => (object)$filter,
                    'page' => (int)$page
                ]
            ]
        ];
    }

    /**
     * The manage category action.
     *
     * @param int $id
     *
     * @Route("/category", name="admin/category")
     * @Access("blog: manage all posts")
     * @Request({"id": "int"})
     *
     * @return array
     */
    public function editCategoryAction($id = 0)
    {
        try {
            if (!$category = Category::where(['id' => $id])->first()) {
                if ($id) {
                    App::abort(404, __('Category not found'));
                }

                $category = Category::create();
            }

            return [
                '$view' => [
                    'title' => $id ? __('Edit Category') : __('Add Category'),
                    'name' => 'blog/admin/category.php'
                ],
                '$data' => [
                    'category' => $category
                ],
                'category' => $category
            ];
        } catch (\Exception $e) {
            App::message()->error($e->getMessage());

            return App::redirect('@blog/admin/categories');
        }
    }

}
