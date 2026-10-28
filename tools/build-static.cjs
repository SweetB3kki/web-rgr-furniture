const fs=require('node:fs');
const path=require('node:path');
const root=path.resolve(__dirname,'..');
const output=path.join(root,'dist');
fs.mkdirSync(output,{recursive:true});
for(const directory of ['assets','documents']) {
    fs.cpSync(path.join(root,'public',directory),path.join(output,directory),{recursive:true});
}
