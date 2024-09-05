import axios from "axios";
import { config } from "./utils";
import { parse } from "csv-parse/sync";
import { readFileSync } from "fs";

interface Discussion {
  id: string;
  title: string;
  user_id: string;
  comment_count: string;
}

const input = readFileSync("discussions.csv", "utf8");

const records: Discussion[] = parse(input, {
  columns: true,
  skip_empty_lines: true,
});

const toDelete: string[] = [];

const hashmap: Map<string, Discussion[]> = new Map();

for (const record of records) {
  const { title, user_id } = record;
  const hash = `${title}---${user_id}`;
  hashmap.set(hash, (hashmap.get(hash) || []).concat(record));
}

for (const [key, values] of hashmap) {
  if (values.length > 1) {
    for (const value of values) {
      if (value.comment_count === "1" || value.comment_count === "0") {
        toDelete.push(value.id);
      }
    }
  }
}

const failed: string[] = [];

for (const id of toDelete) {
  await axios
    .delete(`/api/discussions/${id}`, config)
    .then(() => {
      console.log(`Deleted discussion ${id}`);
    })
    .catch(() => {
      console.log(`Failed to delete discussion ${id}`);
      failed.push(id);
    });
}
