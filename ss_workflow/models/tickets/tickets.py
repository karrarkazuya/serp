# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions
from datetime import date, datetime, timedelta
from markupsafe import Markup
import secrets


class ss_workflow(models.Model):
    _name = 'ssw.tickets'
    _description = 'Ticket'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = "resolve_deadline asc"

    name                     = fields.Char(string='Title', required=True, tracking=True)
    description              = fields.Text('Description', tracking=True)
    context                  = fields.Text('Context', readonly=True)
    
    state                    = fields.Selection([("closed", 'Closed'), ("draft", 'Draft'), ("pending", 'Pending'), ("completed", 'Completed')], default='draft', required=True, tracking=True)
    priority                 = fields.Selection([("1", '⭐️'), ("2", '⭐️⭐️'), ("3", '⭐️⭐️⭐️')], default='1', required=True, tracking=True)
    
    template_id              = fields.Many2one('ssw.tickets.templates', string='Ticket Template', required=True, tracking=True, domain=lambda self: self._get_template_domain())
    created_by               = fields.Many2one('res.partner', string='Created by', tracking=True)
    assigned_to_user         = fields.Many2one('res.partner', string='Assigned User', tracking=True)
    assigned_to_dep          = fields.Many2one('ssw.departments', string='Assigned Department', required=True, tracking=True, domain=lambda self: self._get_department_domain())
    users_can_view           = fields.Many2many('res.partner', string='Users can view')
    inputs                   = fields.One2many('ssw.tickets.inputs', 'ticket_id', string='Inputs', domain=lambda self: self._get_inputs_domain())
    durations                = fields.One2many('ssw.tickets.durations', 'ticket_id', string='Resolve Durations')
    
    resolve_max_duration     = fields.Integer(string='SLA Duration hours', default=168, tracking=True)
    resolve_deadline         = fields.Datetime(string='Resolve deadline', tracking=True)
    resolve_duration         = fields.Integer(string='Resolve duration', default=0, tracking=True)
    resolve_deadline_passed  = fields.Integer(string='SLA Passed', help="How many hours it passed the SLA", default=0, tracking=True)
    is_contact_ticket_only   = fields.Boolean(string='Is Contact Ticket Only', default=False, tracking=True)
    
    assigned_to_me           = fields.Boolean('Is assigned to me', compute='_set_is_assigned_to_me')
    
    assigned_to_user_domain  = fields.Binary("assigned_to_user_domain", default=[], compute='_compute_assigned_to_user_domain', save=False)
    

    # optionals for linking
    optional_partner_id      = fields.Many2one('res.partner', string='Associated contact', tracking=True)
    optional_user_id         = fields.Many2one('res.users', string='Associated user', tracking=True)
    optional_procedure_id    = fields.Many2one('ssw.procedures', string='Parent Procedure', tracking=True)
    optional_ticket_id       = fields.Many2one('ssw.tickets', string='Parent ticket', tracking=True)
    optional_task_id         = fields.Many2one('ssw.proc.tasks', string='Parent task', tracking=True)
    
    ssw_ticket_count          = fields.Integer(string="Tickets count", compute="_compute_ssw_tickets_count")
    ssw_ticket_ids            = fields.One2many('ssw.tickets', 'optional_ticket_id', string="Tickets")
    
    ssw_procedure_count       = fields.Integer(string="Tickets count", compute="_compute_ssw_procedures_count")
    ssw_procedure_ids         = fields.One2many('ssw.procedures', 'optional_ticket_id', string="Tickets")
    

    deleted                  = fields.Boolean(string='Is Deleted', default=False, tracking=True)

    share_enabled            = fields.Boolean(string='Sharing Enabled', default=False, tracking=True)
    share_token              = fields.Char(string='Share Token', readonly=True, copy=False)
    share_message            = fields.Text(string='Share Message')
    share_url                = fields.Char(string='Share URL', compute='_compute_share_url')
    

    
    @api.depends('share_token', 'share_enabled')
    def _compute_share_url(self):
        base_url = self.env['ir.config_parameter'].sudo().get_param('web.base.url')
        for record in self:
            if record.share_token and record.share_enabled:
                record.share_url = f"{base_url}/ticket/share/{record.share_token}"
            else:
                record.share_url = False

    def action_toggle_share(self):
        self.ensure_one()
        if self.share_enabled:
            self.write({'share_enabled': False})
        else:
            token = self.share_token or secrets.token_urlsafe(32)
            self.write({'share_enabled': True, 'share_token': token})

    @api.depends('ssw_ticket_ids')
    def _compute_ssw_tickets_count(self):
        for item in self:
            item.ssw_ticket_count = self.env['ssw.tickets'].sudo().search_count([('optional_ticket_id', '=', item.id), ('deleted', '=', False)])
        
    def action_get_tickets_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.tickets',
            'domain': [('optional_ticket_id', '=', self.id)],
            'context': {
                'default_optional_ticket_id': self.id,
                'create': True,
            }
        }
        
        
    @api.depends('ssw_procedure_ids')
    def _compute_ssw_procedures_count(self):
        for item in self:
            item.ssw_procedure_count = self.env['ssw.procedures'].sudo().search_count([('optional_ticket_id', '=', item.id), ('deleted', '=', False)])
        
    def action_get_procedures_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.procedures',
            'domain': [('optional_ticket_id', '=', self.id)],
            'context': {
                'default_optional_ticket_id': self.id,
                'create': True,
            }
        }
        
    def _set_is_assigned_to_me(self):
        for record in self:
            record.assigned_to_me = False
            if record.assigned_to_user.id == self.env.user.partner_id.id:
                record.assigned_to_me = True
    
    def _get_template_domain(self):
        current_user   = self.env.user
        ticket_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        groups_can_see = ticket_user.groups_can_see.ids  # Get the IDs of the groups
        default_department_id = ticket_user.default_department.id
        
        contact_type_ids = current_user.contact_type.ids
        
        domain = [
            ('enabled', '=', True), 
            ('deleted', '=', False), 
            ('is_contact_ticket_only', '=', False), 
            ('default_group', 'in', groups_can_see), 
            ('departments_can_create', 'in', [default_department_id])
        ]

        if contact_type_ids and len(contact_type_ids) > 0:
            domain.append(('contact_types_can_create', 'in', contact_type_ids))
        else:
            domain.append(('id', '=', 0))  # Ensures no record is returned
        
        return domain
    
    @api.model
    def action_templates_create(self):
        """Return action with dynamic domain"""
        action = self.env.ref("ss_workflow.action_templates_create").read()[0]
        action["domain"] = self._get_template_domain()
        return action

    @api.depends('assigned_to_dep')
    def _compute_assigned_to_user_domain(self):
        partner_ids = []
        for record in self:
            department_id = record.assigned_to_dep.id
            users = self.env['ssw.users'].sudo().search([('default_department', '=', department_id)])
            for user in users:
                partner_ids.append(user.partner_id.id)
            if len(partner_ids) == 0:
                record.assigned_to_user_domain = [("id", "=", -10)]
            else:
                record.assigned_to_user_domain = [("id", "in", partner_ids)]
            
        
    def _get_department_domain(self):
        user = self.env['ssw.users'].sudo().search([('user_id', '=', self.env.user.id)], limit=1)
        return [('id', 'in', user.departments_can_assign.ids)]
    
    def _get_inputs_domain(self):
        current_user   = self.env.user
        ticket_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        default_department_id = ticket_user.default_department.id

        return [('deleted', '=', False), '|', ('departments_can_view', '=', False), ('departments_can_view', 'in', [default_department_id])]


    def create(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            current_user   = self.env.user
            if current_user:
                payload['state'] = "pending"
                ticket_user = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
                partner = current_user.partner_id
                groups_can_see = ticket_user.groups_can_see.ids  # Get the IDs of the groups
                default_department_id = ticket_user.default_department.id
                template_payload = [
                    ('id', '=', payload['template_id']), 
                    ('enabled', '=', True), 
                    ('deleted', '=', False), 
                    ('default_group', 'in', groups_can_see), 
                    ('departments_can_create', 'in', [default_department_id]), 
                    ('is_contact_ticket_only', '=', False)]
                
                if current_user.contact_type.ids and len(current_user.contact_type.ids) > 0:
                    template_payload.append(('contact_types_can_create', 'in', current_user.contact_type.ids))
                else:
                    template_payload.append(('id', '=', 0))
            else:
                partner = self.env['res.partner'].browse([payload['created_by']])
                ticket_user = self.env['ssw.users'].sudo().search([('partner_id', '=', partner.id)], limit=1)
                if ticket_user: 
                    groups_can_see = ticket_user.groups_can_see.ids
                    groups_domain = ('default_group', 'in', groups_can_see)
                else:
                    groups_domain = ('default_group', '=', False)
                template_payload = [
                    ('id', '=', payload['template_id']), 
                    ('enabled', '=', True), 
                    ('deleted', '=', False), 
                    groups_domain, 
                    ('is_contact_ticket_only', '=', True)]
                
                if partner.contact_type.ids and len(partner.contact_type.ids) > 0:
                    template_payload.append(('contact_types_can_create', 'in', partner.contact_type.ids))
                else:
                    template_payload.append(('id', '=', 0))

            template = self.env['ssw.tickets.templates'].sudo().search(template_payload, limit=1)
            
            if not template:
                raise exceptions.ValidationError("You do not have access for this template " + str(template_payload))
            payload['created_by']        = partner.id
            payload['assigned_to_dep']   = template.default_department.id
            payload['resolve_deadline']  = datetime.now() + timedelta(hours=template.resolve_max_duration)
            payload['users_can_view']  = [partner.id]
            payload['is_contact_ticket_only'] = template.is_contact_ticket_only
            payload['resolve_max_duration'] = template.resolve_max_duration
            
            ticket_created = super().create(payload)
            
            # we add the inputs
            for input in template.sudo().inputs:
                
                new_input = self.env['ssw.tickets.inputs'].sudo().create({
                    "ticket_id": ticket_created.id,
                    "name": input.name,
                    "departments_can_view":   input.departments_can_view,
                    "departments_can_change": input.departments_can_change,
                    "type": input.type,
                    "reference_model_name": input.reference_model_name,
                    "allow_multiple_model_values": input.allow_multiple_model_values,
                })

                if len(input.inputs_sub) > 0:
                    for sub in input.sudo().inputs_sub:
                        self.env['ssw.tickets.inputs.subs'].sudo().create({
                            "input_id": new_input.id,
                            "name": sub.name
                        })
                    
                ticket_created.write({
                    'inputs': [(4, new_input.id)]
                })
                
            ticket_created.write({
                'users_can_view': [(4, partner.id)]
            })
            
            managers = self.env['ssw.managers'].sudo().search([
                ('default_department', 'in', [ticket_created.assigned_to_dep.id]), ('deleted', '=', False)
            ])
            
            template = ticket_created.template_id
            
            for manager in managers:
                ticket_created.write({
                    'users_can_view': [(4, manager.user_id.partner_id.id)]
                })
            
            # we add the users who can see
            users_with_dep = self.env['ssw.users'].sudo().search([("default_department", "=", template.default_department.id), ('deleted', '=', False)])
            for memeber in users_with_dep:
                if memeber.partner_id:
                    ticket_created.write({
                        'users_can_view': [(4, memeber.partner_id.id)]
                    })
            
            return ticket_created
        
    def write(self, vals_list):
        if not isinstance(vals_list, list):
            vals_list = [vals_list]
        for payload in vals_list:
            if 'state' in payload:
                payload['resolve_duration'] = 0
                if payload['state'] == 'completed':
                    payload['resolve_duration'] = (datetime.now() - self.create_date).total_seconds() / 3600
                    
                    payload['resolve_deadline_passed'] = payload['resolve_duration'] - self.resolve_max_duration
                    if payload['resolve_deadline_passed'] < 0:
                        payload['resolve_deadline_passed'] = 0
                 
            previous_resolve_duration = (datetime.now() - self.write_date).total_seconds() / 3600
            last_department = self.assigned_to_dep 
            last_user = self.assigned_to_user 
            
            result = super().write(payload)
            if 'assigned_to_dep' in payload and self.assigned_to_dep:
                dep_id = self.assigned_to_dep.id

                users_with_dep = self.env['ssw.users'].sudo().search([
                    ('default_department', '=', dep_id),
                    ('deleted', '=', False),
                    ('partner_id', '!=', False),
                ])

                managers = self.env['ssw.managers'].sudo().search([
                    ('default_department', '=', dep_id),
                    ('deleted', '=', False),
                    ('user_id.partner_id', '!=', False),
                ])

                user_partner_ids = set(users_with_dep.mapped('partner_id.id'))
                manager_partner_ids = set(managers.mapped('user_id.partner_id.id'))

                all_partner_ids = list(user_partner_ids | manager_partner_ids)

                if all_partner_ids:
                    self.write({
                        'users_can_view': [(4, pid) for pid in all_partner_ids]
                    })

                message = f"This ticket was assigned to your department ({self.name})"
                for partner_id in user_partner_ids:
                    self.notify_user(partner_id=partner_id, message=message)
                      
            if 'assigned_to_user' in payload and payload['assigned_to_user']:
                self.write({
                    'users_can_view': [(4, payload['assigned_to_user'])]
                })
                partner = self.env['res.partner'].sudo().search([
                    (
                        'id', '=', payload['assigned_to_user']
                    )
                ], limit=1)
                self.notify_user(partner_id=partner.id, message="This task was assigned to you (" + str(self.name) + ")")
                
                
            if 'assigned_to_dep' in payload or 'assigned_to_user' in payload or self.state == "completed":      
                if last_department:
                    last_department = last_department.id
                if last_user:
                    last_user = last_user.id
                
                self.env['ssw.tickets.durations'].sudo().create({
                    "ticket_id": self.id,
                    "department_id": last_department,
                    "user_id": last_user,
                    "duration": previous_resolve_duration
                })
                
            if 'users_can_view' in payload and (payload["users_can_view"] == [[3, self.env.user.partner_id.id]] or len(self.users_can_view) == 0):
                raise exceptions.ValidationError("You can not remove yourself from the list")
            
            if 'users_can_view' in payload:
                for input in self.inputs:
                    input._handle_extra_modules()
            return result
        
    
    
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True

    def claim_task(self):
        for record in self:
            record.write({'assigned_to_user': self.env.user.partner_id.id})
            
    def un_claim_task(self):
        for record in self:
            record.write({'assigned_to_user': False})
            
    
    def complete_ticket(self):
        for record in self:
            record.write({'state': 'completed'})
            
    def close_ticket(self):
        for record in self:
            record.write({'state': 'closed'})
            
    def pending_ticket(self):
        for record in self:
            record.write({'state': 'pending'})
            
    def view_ticket(self):
        for record in self:
            return {
                'name': 'Ticket Form',
                'type': 'ir.actions.act_window',
                'res_model': 'ssw.tickets',  # Target model
                'view_mode': 'form',
                'res_id': record.id,  # ID of the record to open
                'target': 'current',  # Open in current window (or use 'new' for popup)
            }
            
            
    def notify_user(self, partner_id, message):
        self.ensure_one()
        
        bot_partner = self.env.ref('base.partner_root')
        partner = self.env['res.partner'].browse(partner_id)
        
        
        recipient_user = self.env['res.users'].sudo().search([('partner_id', '=', partner.id)], limit=1)
        if not recipient_user:
            return True
        
        channel = self.env['discuss.channel'].with_user(recipient_user.id).sudo().channel_get([bot_partner.id])

        # Some versions return a dict; normalize to a recordset
        if isinstance(channel, dict):
            channel = self.env['discuss.channel'].sudo().browse(channel.get('id'))
            
        record_url = f"/web#id={self.id}&model={self._name}&view_type=form"
            
        body = Markup(f"""
            <p>{message}</p>
            <br>
            <p>
                <a href="{record_url}" class="o_channel_redirect">
                    View
                </a>
            </p>
        """)
            
        # Send chat message
        channel.message_post(
            body=body,
            author_id = bot_partner.id,
            message_type = "comment",
            subtype_xmlid = "mail.mt_comment",
        )
        
        
    def updateContext(self):
        self.ensure_one()
        context = str(self.id) + ' ' + str(self.name) + ' ' + str(self.description)
        
        for input in self.inputs:
            context = context + ' ' + str(input.result_value)
            
        super().write({
            'context': context
        })


class ssw_tickets_deurations(models.Model):
    _name = 'ssw.tickets.durations'
    _description = 'Ticket Durations'

    ticket_id                     = fields.Many2one('ssw.tickets', string='Ticket', required=True)
    department_id                 = fields.Many2one('ssw.departments', string='Department')
    user_id                       = fields.Many2one('res.partner', string='User')
    duration                      = fields.Float(string='Resolve duration (hours)', required=True)

    def write(self, vals):
        raise exceptions.ValidationError("Not modifiable")
        return False
    
    def unlink(self):
        raise exceptions.ValidationError("Not modifiable")
        return super().unlink()
