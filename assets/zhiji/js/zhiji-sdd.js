var con = document.getElementById("xiaojudeng");
var width=document.body.clientWidth;
var j = Math.ceil(width/15) + 1;
for(var i=0; i<=j; i++){
       var crli = document.createElement("li");
    con.appendChild(crli);
}
