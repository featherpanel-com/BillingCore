# Plugin Release Workflow

This GitHub Actions workflow automatically builds and releases your FeatherPanel plugin when you create a new GitHub release.

## Features

- ✅ Automatically builds frontend components (`yarn build`)
- ✅ Creates password-protected `.fpa` file using `.featherexport` exclusions
- ✅ Uploads to FeatherPanel Cloud API
- ✅ Attaches `.fpa` file to GitHub release
- ✅ Supports both GitHub releases and manual workflow dispatch

## Setup

### 1. Configure GitHub Secrets

Add these secrets to your GitHub repository:

- `CLOUD_TEAM_UUID`: Your team UUID from the cloud dashboard (e.g., `21e6d47f-4b0f-419f-8daf-ce6496a5d676`)
- `CLOUD_API_TOKEN`: Your API authentication token

To add secrets:
1. Go to your repository → Settings → Secrets and variables → Actions
2. Click "New repository secret"
3. Add each secret

### 2. Update Package ID

If your plugin has a different package ID than the default (31), update it in `.github/workflows/release.yml`:

```yaml
# Default package ID for billingcore (update this if needed)
PACKAGE_ID="31"
```

### 3. Configure `.featherexport`

Make sure your `.featherexport` file includes all files/directories that should be excluded from the export:

```
*/node_modules/*
/demo/*
banner.png 
README.md
*/App/*
```

## Usage

### Automatic Release (Recommended)

1. Update `version` in `conf.yml`
2. Commit and push your changes
3. Create a new GitHub release:
   - Go to Releases → Draft a new release
   - Create a new tag (e.g., `v1.0.1`)
   - Add release notes (these will be used as the changelog)
   - Publish the release

The workflow will automatically:
- Build the frontend
- Create the `.fpa` file
- Upload to cloud API
- Attach `.fpa` to the release

### Manual Workflow Dispatch

1. Go to Actions → Build and Release Plugin
2. Click "Run workflow"
3. Fill in the form:
   - **Version**: Plugin version (e.g., `1.0.1`)
   - **Package ID**: Your package ID from cloud API
   - **Changelog**: Release notes (optional)

## How It Works

1. **Frontend Build**: Installs dependencies and runs `yarn build` in `Frontend/App`
2. **Metadata Extraction**: Reads `conf.yml` to extract:
   - Version
   - Identifier
   - Dependencies
   - Panel version requirements
3. **Export**: Creates `.fpa` file using:
   - Password: `featherpanel_development_kit_2025_addon_password`
   - Exclusions from `.featherexport`
4. **Upload**: Sends to cloud API with all metadata
5. **Artifact**: Saves `.fpa` file as GitHub artifact and attaches to release

## Troubleshooting

### Build Fails

- Check that `Frontend/App/package.json` exists
- Ensure `yarn build` script is defined
- Verify Node.js version compatibility

### Upload Fails

- Verify `CLOUD_TEAM_UUID` and `CLOUD_API_TOKEN` secrets are set
- Check that package ID is correct
- Ensure API token has upload permissions

### Export Missing Files

- Review `.featherexport` exclusions
- Check that frontend build output is included
- Verify `Frontend/Components` directory exists after build

## Local Testing

You can test the build script locally:

```bash
cd backend/storage/addons/billingcore
chmod +x build-release.sh
./build-release.sh
```

This will create a `.fpa` file in the plugin directory.