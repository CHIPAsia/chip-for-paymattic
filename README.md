<img src="./assets/logo.svg" alt="drawing" width="50"/>

# CHIP for Paymattic

This module adds CHIP payment method option to your Paymattic.

## Installation

* [Download zip file of Paymattic plugin.](https://github.com/CHIPAsia/chip-for-paymattic/archive/refs/heads/main.zip)
* Log in to your WordPress admin panel and go: **Plugins** -> **Add New**
* Select **Upload Plugin**, choose zip file you downloaded in step 1 and press **Install Now**
* Activate plugin

## Configuration

Set the **Brand ID** and **Secret Key** in the plugins settings.


## Wordpress Collaboration workflow
Welcome to Wordpress Collaboration Workflow


**Index**

- [Working on new feature](#working-on-new-feature)
- [Deploying to SVN repository](#deploying-to-svn-repository)
- [Notes](#notes)



### Working on new feature

1. Push feature branch to remote
  ```
  git push origin <feature_branch>
  ```
2. Open Pull Request to merge your branch into `main` branch
3. Once approved, merge your branch into main branch


### Deploying to SVN repository

1. Create tag based on whether it is for uploading files only or releasing a stable version

- Upload files
  ```
  git tag paymattic-upload-v<[0-9]+.[0-9]+.[0-9]+>
  ```

- Release stable version
  ```
  git tag paymattic-release-v<[0-9]+.[0-9]+.[0-9]+>
  ```


### Notes

1. Tag must be created on the `main` branch as `main` branch will be the official branch linked to SVN repository
2. SVN commit is based on the latest git commit
3. Ensure the `changelog.txt` and `readme.txt` in tag folder document the changes
4. Ensure trunk folder have the latest tag files before deploying to SVN repository

## Other

Facebook: [Merchants & DEV Community](https://www.facebook.com/groups/3210496372558088)
