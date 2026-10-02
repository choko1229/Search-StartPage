const formats={jpg:['image','image/jpeg'],jpeg:['image','image/jpeg'],png:['image','image/png'],gif:['image','image/gif'],webp:['image','image/webp'],avif:['image','image/avif'],mp4:['video','video/mp4'],webm:['video','video/webm']};
export async function inspectBackgroundFile(file) {
    if(!(file instanceof Blob)||typeof file.name!=='string'||!file.name||file.name.length>255||/[\\/\x00-\x1f\x7f]/.test(file.name)||/\.(php\d*|phtml|phar|cgi|exe|html?|svg)(\.|$)/i.test(file.name))throw new Error('INVALID_UPLOAD_NAME');
    const extension=file.name.split('.').at(-1).toLowerCase(),format=formats[extension];
    if(!format)throw new Error('UNSUPPORTED_BACKGROUND_FORMAT');
    if(!file.size)throw new Error('INVALID_UPLOAD');
    if(file.size>(format[0]==='image'?25:500)*1024*1024)throw new Error('BACKGROUND_TOO_LARGE');
    const header=new Uint8Array(await file.slice(0,64).arrayBuffer());
    const ascii=(start,length)=>String.fromCharCode(...header.slice(start,start+length));
    const mime=header[0]===255&&header[1]===216&&header[2]===255?'image/jpeg'
        :header.length>=24&&[137,80,78,71,13,10,26,10].every((byte,index)=>header[index]===byte)?'image/png'
        :['GIF87a','GIF89a'].includes(ascii(0,6))?'image/gif'
        :ascii(0,4)==='RIFF'&&ascii(8,4)==='WEBP'?'image/webp'
        :ascii(4,4)==='ftyp'&&(/avif|avis/.test(ascii(8,56)))?'image/avif'
        :ascii(4,4)==='ftyp'?'video/mp4'
        :header[0]===26&&header[1]===69&&header[2]===223&&header[3]===163&&ascii(0,64).includes('webm')?'video/webm':null;
    if(mime!==format[1])throw new Error('BACKGROUND_MIME_MISMATCH');
    return {type:format[0],mime,fileSize:file.size};
}
