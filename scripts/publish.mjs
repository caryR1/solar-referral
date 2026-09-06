#!/usr/bin/env node
// Pushes pages and media to solar.gemzonline.com via the WordPress REST API,
// using the Application Password credentials in .secrets/solar-gemzonline-credentials.txt.
//
// Usage:
//   node scripts/publish.mjs page --title "About" --slug about --content-file content/about.html [--status draft|publish] [--id 123]
//   node scripts/publish.mjs media --file site-assets/staging/hero.png [--alt "Solar panels on a house roof"]
//   node scripts/publish.mjs whoami

import { readFileSync } from "node:fs";
import { basename } from "node:path";

const CREDS_PATH = new URL("../.secrets/solar-gemzonline-credentials.txt", import.meta.url);

function loadCredentials() {
  const [site, username, appPassword] = readFileSync(CREDS_PATH, "utf8")
    .split("\n")
    .map((line) => line.trim())
    .filter(Boolean);
  if (!site || !username || !appPassword) {
    throw new Error("Expected 3 lines in solar-gemzonline-credentials.txt: site, username, application password");
  }
  return { site, username, appPassword };
}

function authHeader({ username, appPassword }) {
  const token = Buffer.from(`${username}:${appPassword.replace(/\s+/g, "")}`).toString("base64");
  return `Basic ${token}`;
}

function parseArgs(argv) {
  const args = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg.startsWith("--")) {
      const key = arg.slice(2);
      const next = argv[i + 1];
      if (next === undefined || next.startsWith("--")) {
        args[key] = true;
      } else {
        args[key] = next;
        i++;
      }
    } else {
      args._.push(arg);
    }
  }
  return args;
}

async function wpRequest(creds, path, { method = "GET", body, headers = {} } = {}) {
  const res = await fetch(`https://${creds.site}/wp-json${path}`, {
    method,
    headers: { Authorization: authHeader(creds), ...headers },
    body,
  });
  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    json = text;
  }
  if (!res.ok) {
    throw new Error(`${method} ${path} -> HTTP ${res.status}: ${typeof json === "string" ? json : JSON.stringify(json)}`);
  }
  return json;
}

async function cmdWhoami(creds) {
  const me = await wpRequest(creds, "/wp/v2/users/me");
  console.log(`Authenticated as ${me.name} (id ${me.id})`);
}

async function cmdPage(creds, args) {
  if (!args.title || !args["content-file"]) {
    throw new Error("page requires --title and --content-file");
  }
  const content = readFileSync(args["content-file"], "utf8");
  const payload = {
    title: args.title,
    content,
    status: args.status || "draft",
  };
  if (args.slug) payload.slug = args.slug;

  const path = args.id ? `/wp/v2/pages/${args.id}` : "/wp/v2/pages";
  const method = args.id ? "POST" : "POST"; // POST also updates when hitting /pages/{id}
  const page = await wpRequest(creds, path, {
    method,
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  console.log(`Page saved: id=${page.id} status=${page.status} link=${page.link}`);
}

async function cmdMedia(creds, args) {
  if (!args.file) throw new Error("media requires --file");
  const fileBuffer = readFileSync(args.file);
  const filename = basename(args.file);
  const ext = filename.split(".").pop().toLowerCase();
  const mime = { png: "image/png", jpg: "image/jpeg", jpeg: "image/jpeg", webp: "image/webp" }[ext] || "application/octet-stream";

  const media = await wpRequest(creds, "/wp/v2/media", {
    method: "POST",
    headers: {
      "Content-Type": mime,
      "Content-Disposition": `attachment; filename="${filename}"`,
    },
    body: fileBuffer,
  });

  if (args.alt) {
    await wpRequest(creds, `/wp/v2/media/${media.id}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ alt_text: args.alt }),
    });
  }
  console.log(`Media uploaded: id=${media.id} url=${media.source_url}`);
}

async function main() {
  const [command, ...rest] = process.argv.slice(2);
  const args = parseArgs(rest);
  const creds = loadCredentials();

  switch (command) {
    case "whoami":
      return cmdWhoami(creds);
    case "page":
      return cmdPage(creds, args);
    case "media":
      return cmdMedia(creds, args);
    default:
      console.error("Usage: publish.mjs <whoami|page|media> [options]");
      process.exit(1);
  }
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});
