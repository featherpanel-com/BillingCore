#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PASSWORD="featherpanel_development_kit_2025_addon_password"
TEMP_DIR=$(mktemp -d)
EXPORT_FILE="${TEMP_DIR}/plugin.fpa"

echo -e "${GREEN}Starting plugin build process...${NC}"

# Step 1: Build frontend
if [ -d "${PLUGIN_DIR}/Frontend/App" ]; then
    echo -e "${YELLOW}Building frontend...${NC}"
    cd "${PLUGIN_DIR}/Frontend/App"
    
    # Install dependencies - prefer pnpm
    if [ -f "pnpm-lock.yaml" ]; then
        echo -e "${YELLOW}Installing with pnpm...${NC}"
        # Try frozen lockfile first, fallback to regular install if config mismatch
        if ! pnpm install --frozen-lockfile 2>&1; then
            echo -e "${YELLOW}Lockfile config mismatch detected, updating lockfile...${NC}"
            pnpm install --no-frozen-lockfile
        fi
        BUILD_CMD="pnpm build"
    elif [ -f "package-lock.json" ]; then
        echo -e "${YELLOW}Installing with npm...${NC}"
        npm ci || npm install
        BUILD_CMD="npm run build"
    elif [ -f "yarn.lock" ]; then
        echo -e "${YELLOW}Installing with yarn...${NC}"
        yarn install --frozen-lockfile || yarn install
        BUILD_CMD="yarn build"
    else
        echo -e "${YELLOW}No lockfile found, using pnpm...${NC}"
        pnpm install || npm install
        BUILD_CMD="pnpm build || npm run build"
    fi
    
    # Build
    echo -e "${YELLOW}Building frontend...${NC}"
    eval "${BUILD_CMD}"
    
    echo -e "${GREEN}Frontend build completed${NC}"
    cd "${PLUGIN_DIR}"
else
    echo -e "${YELLOW}No Frontend/App directory found, skipping frontend build${NC}"
fi

# Step 2: Parse conf.yml to get plugin metadata
echo -e "${YELLOW}Reading plugin configuration...${NC}"
CONF_FILE="${PLUGIN_DIR}/conf.yml"
if [ ! -f "${CONF_FILE}" ]; then
    echo -e "${RED}Error: conf.yml not found${NC}"
    exit 1
fi

# Extract version from conf.yml (simple yaml parsing)
PLUGIN_VERSION=$(grep -E "^\s*version:" "${CONF_FILE}" | sed -E 's/.*version:\s*["'\'']?([^"'\'']+)["'\'']?/\1/' | tr -d ' ')
PLUGIN_IDENTIFIER=$(grep -E "^\s*identifier:" "${CONF_FILE}" | sed -E 's/.*identifier:\s*["'\'']?([^"'\'']+)["'\'']?/\1/' | tr -d ' ')

if [ -z "${PLUGIN_VERSION}" ]; then
    echo -e "${RED}Error: Could not extract version from conf.yml${NC}"
    exit 1
fi

if [ -z "${PLUGIN_IDENTIFIER}" ]; then
    echo -e "${RED}Error: Could not extract identifier from conf.yml${NC}"
    exit 1
fi

echo -e "${GREEN}Plugin: ${PLUGIN_IDENTIFIER} v${PLUGIN_VERSION}${NC}"

# Step 3: Parse .featherexport for exclusions
EXCLUSIONS=()
EXPORT_IGNORE="${PLUGIN_DIR}/.featherexport"
if [ -f "${EXPORT_IGNORE}" ]; then
    echo -e "${YELLOW}Reading .featherexport exclusions...${NC}"
    while IFS= read -r line || [ -n "$line" ]; do
        # Trim whitespace
        line=$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        
        # Skip empty lines and comments
        if [ -z "$line" ] || [[ "$line" =~ ^# ]]; then
            continue
        fi
        
        # Remove inline comments
        line=$(echo "$line" | sed 's/#.*$//' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
        
        if [ -n "$line" ]; then
            EXCLUSIONS+=("$line")
        fi
    done < "${EXPORT_IGNORE}"
    echo -e "${GREEN}Found ${#EXCLUSIONS[@]} exclusion pattern(s)${NC}"
fi

# Always exclude .featherexport itself
EXCLUSIONS+=(".featherexport")

# Step 4: Create .fpa file
echo -e "${YELLOW}Creating .fpa archive...${NC}"
cd "${PLUGIN_DIR}"

# Build zip command with exclusions
# Match the approach from PluginsController::export()
# Use * instead of . to avoid including the current directory itself
if [ ${#EXCLUSIONS[@]} -gt 0 ]; then
    echo -e "${YELLOW}Excluding ${#EXCLUSIONS[@]} pattern(s) from .featherexport:${NC}"
    for pattern in "${EXCLUSIONS[@]}"; do
        echo -e "  - ${pattern}"
    done
    
    # Build exclusion arguments array
    EXCLUSION_ARGS=()
    for pattern in "${EXCLUSIONS[@]}"; do
        # Remove leading slash if present (zip patterns are relative to current dir)
        PATTERN=$(echo "$pattern" | sed 's|^/||')
        EXCLUSION_ARGS+=("-x" "$PATTERN")
    done
    
    # Run zip with exclusions
    zip -r -P "${PASSWORD}" "${EXPORT_FILE}" * "${EXCLUSION_ARGS[@]}"
else
    # No exclusions, simple zip
    zip -r -P "${PASSWORD}" "${EXPORT_FILE}" *
fi

if [ ! -f "${EXPORT_FILE}" ]; then
    echo -e "${RED}Error: Failed to create .fpa file${NC}"
    rm -rf "${TEMP_DIR}"
    exit 1
fi

# Move to final location
FINAL_EXPORT="${PLUGIN_DIR}/${PLUGIN_IDENTIFIER}-${PLUGIN_VERSION}.fpa"
mv "${EXPORT_FILE}" "${FINAL_EXPORT}"

echo -e "${GREEN}Created: ${FINAL_EXPORT}${NC}"

# Output file path for GitHub Actions
if [ -n "${GITHUB_OUTPUT}" ]; then
    echo "export_file=${FINAL_EXPORT}" >> "${GITHUB_OUTPUT}"
    echo "plugin_version=${PLUGIN_VERSION}" >> "${GITHUB_OUTPUT}"
    echo "plugin_identifier=${PLUGIN_IDENTIFIER}" >> "${GITHUB_OUTPUT}"
fi

# Cleanup temp dir
rm -rf "${TEMP_DIR}"

echo -e "${GREEN}Build completed successfully!${NC}"

