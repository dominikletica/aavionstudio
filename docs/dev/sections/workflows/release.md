# Release workflow

Status: Draft  
Updated: 2025-10-29

This guide describes how to create a deployable archive of aavion Studio.

Note: The repository may ship with a placeholder `release.json` for tooling/tests. The release script generates a fresh `release.json` with version metadata.

## Prerequisites
- PHP CLI with the same version as production
- Composer
- zip utility
- Optional: Tailwind bundle prerequisites (`bin/init_repository` already installs them)

## Steps
1. Ensure the working tree is clean and up to date (`git status`).
2. Bootstrap dependencies if you have not already:
   ```bash
   bin/init_repository
   ```
3. Run the release script with target environment, version, and channel tag:
   ```bash
   bin/release prod 1.0.0 stable
   ```
   - `environment` sets `APP_ENV` during build (e.g. `prod`, `dev`)
   - `version` is the semantic version string (Major.Minor.Sub)
   - `channel` captures release track (in `stable`, `testing`, `dev`, `alpha`, `beta`)
4. The script will:
   - Install Composer dependencies (`--no-dev` outside `dev`)
   - Refresh importmap assets, build Tailwind CSS, and remove downloaded Tailwind binaries
   - Warm the Symfony cache for the chosen environment
   - Stage a clean copy in `build/<env>-<version>-<channel>/`
   - Write `RELEASE.json` containing version metadata (commit, timestamp, channel)
   - Package the archive at `build/aavionstudio-<version>-<channel>.zip`
5. Distribute the generated ZIP. The installer will generate real secrets (`APP_SECRET`, etc.) on first run.

## Customisation
- Modify `bin/release` to add/remove files from the bundle (adjust rsync excludes).
- Pass other environments (e.g. `stage`, `qa`) as first argument.
- Hook the script into CI to publish tagged builds automatically.

## Post-release tasks
- Tag the commit in Git (`git tag v0.1.0 && git push --tags`).
- Update release notes / changelog as needed.
- Monitor production logs after deployment.
