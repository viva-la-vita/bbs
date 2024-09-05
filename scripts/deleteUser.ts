import axios from "axios";
import { config } from "./utils";

let offset = 8750;

for (let i = 0; i < 200; i++) {
  console.log(`Offset: ${offset}`);
  const res = await axios.get(
    `/api/users?filter[q]&page[limit]=50&page[offset]=${offset}`,
    config
  );

  const { data, links } = res.data;

  const promises: Promise<any>[] = [];

  for (const { id, attributes } of data) {
    const { isEmailConfirmed, discussionCount, commentCount } = attributes;
    if (!isEmailConfirmed && discussionCount === 0 && commentCount === 0) {
      console.log(id);
      if (id > 35000) continue;
      const deleteResult = axios.delete(`/api/users/${id}`, config);
      promises.push(deleteResult);
    }
  }

  Promise.all(promises)
  .then(() => {})
  .catch(() => {});
  offset += (50 - promises.length);

  console.log(`Deleted ${promises.length} users`);
}
