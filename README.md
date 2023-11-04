# plg_system_onuserghsvs
Joomla system plugin. Performs additional actions when users are edited or saved.

## Features
1. When a new user is created in the (!)backend(!), inform Super-Users by email? Including the login details of the new user.
2. The field `Name` is checked for prohibited characters or character strings during registration in the frontend. There is only a simple filtering on the code side. A distinction is NOT made between upper and lower case letters. If the name contains forbidden characters (strings), the registration is aborted with an error message.
3. Selected users are not allowed to change their user data when editing their profile. An error message is displayed to the user.
4. Joomla 4+: Change/Lower minimum password length of Joomla's configuration settimgs "Users > Password Options".

## Languages
- de-DE
- en-GB

----------------------

# My personal build procedure (WSL 1, Debian, Win 10)

**@since v2022.06.11: Build procedure uses local repo fork of https://github.com/GHSVS-de/buildKramGhsvs**

- Prepare/adapt `./package.json`.
- `cd /mnt/z/git-kram/plg_system_onuserghsvs`

## node/npm updates/installation
- `npm run updateCheck` or (faster) `npm outdated`
- `npm run update` (if needed) or (faster) `npm update --save-dev`
- `npm install` (if needed)

## Build installable ZIP package
- `node build.js`
- New, installable ZIP is in `./dist` afterwards.
- All packed files for this ZIP can be seen in `./package`. **But only if you disable deletion of this folder at the end of `build.js`**.s

#### For Joomla update server
- Use/See `dist/release.txt` as basic release text.
- Create new release with new tag.
- See extracts(!) of the update and changelog XML for update and changelog servers are in `./dist` as well. Check for necessary additions! Then copy/paste.
