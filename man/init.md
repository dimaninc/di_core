## Set up an existing project based on diCMS on your local machine

### Clone the project from git

Let's say, our project is called `project_name`

`git clone git@github.com:organization/project_name.git`

### Set up your local web-server

Create new entry for domain in server's config, name it as the project is named, `project_name` in our example

The root directory should point to `project_name/htdocs`

Don't forget to restart/reload your local web-server to apply the changes

### Install composer libraries

Change directory to `project_name` and run `composer install`

After that run two these scripts:

    sh vendor/dimaninc/di_core/scripts/copy_core_static.sh
    sh vendor/dimaninc/di_core/scripts/create_work_folders.sh

### Create local config

Create new file `/src/ProjectName/Data/Environment.php` and insert there contents below:

    <?php
    namespace ProjectName\Data;
    
    class Environment extends \diCore\Data\Environment
    {
        const mainDomain = 'project_name';
        const initiating = true;
    }

Don't forget to replace `ProjectName` in namespace with your actual namespace (one and only subfolder's name is the namespace)

Also replace `mainDomain` constant value with your domain

### Try to sign in to admin

Now type `http://project_name/_admin/` in browser. Mysql database will be created automatically (mysql user should have login `root` and empty password)

Then navigate to `http://project_name/_admin/db/` and click on `Restore` button near the latest DB dump

Navigate to `http://project_name/_admin/admins/` and check if your admin user exists. If not, create a new one.

Open `/src/ProjectName/Data/Environment.php` in editor and comment out this line `const initiating = true;`

Reload admin page in browser, it should ask you the login and password for admin.

In admin, open left menu item `Settings` and click on `Rebuild cache` link

### Congrats, the project has been set up on your local machine 