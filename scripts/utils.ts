import 'dotenv/config';

export const config = {
  baseURL: "https://bbs.viva-la-vita.org",
  headers: {
    Authorization: `Token ${process.env.TOKEN}; userId=1`,
  },
};
