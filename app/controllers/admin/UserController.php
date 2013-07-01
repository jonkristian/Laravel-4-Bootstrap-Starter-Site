<?php
namespace admin;

use BaseController;
use UserRepositoryInterface;
use RoleRepositoryInterface;
use Auth;
use Lang;
use View;
use Confide;
use Redirect;
use API;
use User;
use Input;
use Session;
use Datatables;

/*
|--------------------------------------------------------------------------
| Admin User Controller
|--------------------------------------------------------------------------
|
| User resource management.
|
*/

class UserController extends BaseController {

    /**
     * User Repository Interface
     *
     * @var UserRepositoryInterface
     */
    protected $users;

    /**
     * Role Repository Interface
     *
     * @var RoleRepositoryInterface
     */
    protected $roles;

    /**
     * Create a new controller instance
     *
     * Inject the repository interfaces.
     *
     * @param UserRepositoryInterface $users
     */
    public function __construct(UserRepositoryInterface $users, RoleRepositoryInterface $roles)
    {
        $this->users = $users;
        $this->roles = $roles;
        $this->meta = array(
            'title' => 'Default',
            'author' => 'Me',
            'keywords' => 'Keywords',
            'description' => 'Description'
        );
    }

    /**
     * Display a listing of the users
     *
     * @return View
     */
    public function index()
    {
        // Get the data needed for the view.
        $meta = $this->meta;
        $meta['title'] = Lang::get('admin/users/title.user_management');

        // There is no need to send any data to the view.
        // The datatables will be calling the getData method.
        return View::make('admin.users.index', compact('meta'));
    }

    /**
     * Show the form for creating a new user
     *
     * @return view
     */
    public function create()
    {
        // Get the data needed for the view.
        $roles = $this->roles->findAll();
        $rules = User::$rules;
        $meta = $this->meta;
        $meta['title'] = Lang::get('admin/users/title.create_a_new_user');

        // Show the create user form page.
        return View::make('admin/users/create', compact('roles', 'meta', 'rules'));
    }

    /**
     * Stores a new user account
     *
     * @return Redirect
     */
    public function store()
    {
        $user = $this->users->store(Input::all());
        // $user = API::post('api/v1/user', Input::all());

        // Handle the repository possible errors
        if(is_array($user)) {
            $errors = $user['message'];
            return Redirect::action('admin\UserController@create')
                            ->withErrors($errors)
                            ->withInput(Input::all());
        } else {
            // Redirect with success message
            $id = $user->id;
            return Redirect::action('admin\UserController@edit', array($id))
                            ->with('success', Lang::get('admin/users/messages.create.success'));
        }
    }

    /**
    * Display the specified user
    *
    * @param  int $id
    *
    * @return method We only want to edit users in the administration.
    */
    public function show($id)
    {
        return $this->edit($id);
    }

    /**
     * Show the form for editing a user
     *
     * @return view
     */
    public function edit($id)
    {
        // Get the data needed for the view.
        $user = $this->users->findById($id);

        // Handle the repository possible errors
        if(is_array($user)) {
            return Redirect::action('admin\UserController@index')
                            ->with('error', Lang::get('admin/users/messages.does_not_exist'));
        }

        $roles = $this->roles->findAll();
        $rules = $user->getUpdateRules();
        $meta = $this->meta;
        $meta['title'] = Lang::get('admin/users/title.user_update');

        // Show the create user form page.
        return View::make('admin/users/edit', compact('user', 'roles', 'meta', 'rules'));
    }

    /**
    * Update the specified user
    *
    * @param int $id
    * @return Response
    */
    public function update($id)
    {
        // Update the user with the PUT request data.
        $user = $this->users->update($id, Input::all());
        // $user = API::put('api/v1/user/' . Auth::user()->id, Input::all());

        // Handle the repository possible errors
        if(is_array($user)) {
            $errors = $user['message'];
            return Redirect::action('admin\UserController@edit')
                            ->withErrors($errors)
                            ->withInput(Input::all());
        } else {
            return Redirect::action('admin\UserController@edit', array($id))
                            ->with('success', Lang::get('admin/users/messages.edit.success'));
        }
    }

    /**
    * Remove the specified user
    *
    * @param int $id
    * @return Response
    */
    public function destroy($id)
    {
        // Delete a user with the corresponding ID.
        $user = $this->users->destroy($id);

        // If the repository throws an exception $user will be a JSON string with our errors.
        if(is_array($user)) {
            $errors = $user['message'];
            if ($user['code'] === '403') {
                $message = Lang::get('admin/users/messages.delete.impossible');
            } else {
                $message = Lang::get('admin/users/messages.delete.error');
            }
            return Redirect::action('admin\UserController@index')
                            ->with('error', $message);
        } else {
            return Redirect::action('admin\UserController@index')
                            ->with('success', Lang::get('admin/users/messages.delete.success'));
        }
    }

    /**
     * Show a list of users formatted for Datatables
     *
     * @return Datatables JSON
     */
    public function data()
    {
        $users = User::leftjoin('assigned_roles', 'assigned_roles.user_id', '=', 'users.id')
                    ->leftjoin('roles', 'roles.id', '=', 'assigned_roles.role_id')
                    ->select(array('users.id', 'users.username','users.email', 'roles.name as rolename', 'users.confirmed', 'users.created_at'));

        return Datatables::of($users)
        // ->edit_column('created_at','{{{ Carbon::now()->diffForHumans(Carbon::createFromFormat(\'Y-m-d H\', $test)) }}}')

        ->edit_column('confirmed','@if($confirmed)
                            Yes
                        @else
                            No
                        @endif')

        ->add_column('actions', '<a href="{{{ URL::to(\'admin/users/\' . $id . \'/edit\' ) }}}" class="iframe btn btn-mini">{{{ Lang::get(\'button.edit\') }}}</a>
                                @if($username == \'admin\')
                                @else
                                    <a href="#delete-modal"
                                        class="delForm btn btn-mini btn-danger"
                                        data-toggle="modal"
                                        data-id="{{{ $id }}}"
                                        data-title="{{{ $username }}}">{{{ Lang::get(\'button.delete\') }}}</a>
                                @endif
            ')

        ->remove_column('id')

        ->make();
    }
}