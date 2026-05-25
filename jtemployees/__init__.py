# -*- coding: utf-8 -*-

from . import controllers
from . import models

from odoo import api, SUPERUSER_ID

def post_init_hook(env):
    """
    # First we set the default general types
    env['jtemployees.ptype'].create({
        'name': 'General'
    })
    
    # Search for all res.partner records
    contact_types = env['jtemployees.ptype'].search([])
    # Search for all res.partner records
    partners = env['res.partner'].search([])
    
    # Search for all res.users records
    users = env['res.users'].search([])
    
    for ctype in contact_types:
        # Update the name for each partner individually
        for partner in partners:
            partner.write({'contact_type': ctype})
        
        # Update the contact_type for each user individually
        for user in users:
            user.write({'contact_type': ctype})
        break
    
    env['res.users'].assign_group_to_user(user_id=2, group_xml_id='jtemployees.group_admin')
    
    """




