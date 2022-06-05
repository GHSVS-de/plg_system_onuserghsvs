# plg_system_onuserghsvs
Joomla system plugin. Performs additional actions when users are edited or saved.

## Restrictions
- Just two actions at the moment:
  - Send information email to `Super User`s when a new user in Joomla backend has been created.
  - Simple check for forbidden characters in `Name` field on registration and block registration if present. Show a message. Only server-side check.
- Just english language files that also contain german translations (lazyness).

----------------------

# My personal build procedure (WSL 1, Debian, Win 10)

**!!Uses build scripts of repo `buildKramGhsvs`!!**

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
