#coding=utf-8

import os
import sys
reload(sys)
sys.setdefaultencoding('gb2312')
import random

#from random import randint

projectPath = "C:\Users\Administrator\Desktop\\aaaaa\\"

if os.path.exists(projectPath):
    pass
else:
    os.mkdir(projectPath)

path = projectPath + "bb.txt"

g=input(u"请任意输入要生成几组数据:\n")
while(g != 0):
    select_list = range(1,34)
    redball = random.sample(select_list,6)
    redball.sort()
    #print str(redball)
    
    select_list = range(1,17)
    blueball = random.sample(select_list,1)
    blueball.sort()
    #print str(blueball) + "\n"
    
    file = open(path, 'a+')
    content = str(redball) + str(blueball) + "\n"
    file.write(content)
    
    g = g - 1
    print str(g)