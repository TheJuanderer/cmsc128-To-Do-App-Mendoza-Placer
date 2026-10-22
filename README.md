# cmsc128-Lab1_CRUD_Mendoza-Placer

## Setup
- open XAMPP
- start apache and MySQL in the XAMPP
- search in a google tab or search engine "http://localhost/phpmyadmin/"
- create a database by clicking on the "new" 
    - name it "todo_db"
    - then click create
- import the todo_list.sql first inside the todo_db
- import the seed.sql
- you can finally add php files

## Running specific pages
    - search in a google tab or search engine "http://localhost/{path of the file you want to run}" , also note that the path starts from the htdocs folder (not including htdocs) 
   


## Multi-account support

The app supports multiple accounts. Each person registers, logs in, and sees and manages only their own tasks.

Flow:

register.php validates input, hashes the password with password_hash(), and creates a row in User. It then redirects to login.php.

login.php looks up the user by user_name, checks the password with password_verify(), calls session_regenerate_id(true) (prevents session fixation), and stores $_SESSION['user_id'].

Every protected page reads the user from $_SESSION['user_id'] and uses it to scope all task queries. The user id always comes from the session, never from the URL or a form field. A user cannot ask for another user's data by editing a request, because the server decides who they are.


# Schema Relationships

User 1 --- N Task: a user can own 0 or many tasks, a task belongs to exactly one user (Task.user_id NOT NULL). This is the link that makes multi-account work.
Category 1 --- N Task: each task has one category.
Priority 1 --- N Task: each task has one priority.
Task is the "many" side of all three relationships. Category and Priority are shared by all users, they are not per-account.


# How wwnership is determined

Ownership check = confirming the logged-in user is allowed to see or change this data.

`login.php`	Redirects to index.php if $_SESSION['user_id'] is already set. Sets user_id only after password_verify() succeeds.	

Entry point of the session

`index.php`	Login gate: redirects to login.php if no session user. Account check: loads the user with WHERE user_id = ? AND deleted_at IS NULL (prepared statement) and logs out if not found. Ownership filter: task query is built with WHERE Task.deleted_at IS NULL AND Task.user_id = $user_id.	

This is the file that enforces "only a specific account's tasks are shown"

`register.php`	No session needed (public page).	Creates the account

CRUD operations -> for all the CRUD operations everytime you do an operation it checks whether there is a `$_SESSION['user_id']` that exists if not, you get booted out back to the login page

