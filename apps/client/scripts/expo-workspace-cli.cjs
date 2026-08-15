const path = require("path");
const Module = require("module");

const clientNodeModules = path.resolve(__dirname, "..", "node_modules");
const existingNodePath = process.env.NODE_PATH;

process.env.NODE_PATH = existingNodePath
  ? `${clientNodeModules}${path.delimiter}${existingNodePath}`
  : clientNodeModules;

Module._initPaths();

require("expo/bin/cli");
