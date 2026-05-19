# -*- coding: utf-8 -*-

from collections import deque

from odoo import models, fields, exceptions, api
from odoo.osv.expression import AND, OR


class ssw_templates(models.Model):
    _name = 'ssw.proc.templates'
    _description = 'task Template'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name                      = fields.Char(string='Title')
    description               = fields.Text()
    
    tasks                     = fields.One2many('ssw.proc.templates.tasks', 'template_id', string='Tasks', tracking=True, domain=[('deleted', '=', False)])
    default_group             = fields.Many2one('ssw.groups', string='Allowed group', help="Only users of those groups can view", tracking=True, required=True)
    
    departments_can_create    = fields.Many2many('ssw.departments', string='Departments can create', tracking=True)
    resolve_max_duration      = fields.Integer(string='SLA Duration hours', default=168, required=True, tracking=True)
    visible_to_current_user   = fields.Boolean('Is this template visible to current user', compute='_set_visibility_to_user')
    creator_see_tasks         = fields.Boolean('Allow creator to access tasks', help="When enabled the creator will be able to access all tasks of the procedure", default=False)
     
    flowchart_view = fields.Html(
        string='Preview',
        sanitize=False,
        compute='_compute_flowchart_view',
    )

    def _compute_flowchart_view(self):
        for rec in self:
            if not rec.id:
                rec.flowchart_view = """
                    <div style="padding: 24px; border: 1px dashed #cbd5e1; border-radius: 12px; color: #475569;">
                        Save the template to preview its flowchart.
                    </div>
                """
                continue

            rec.flowchart_view = """
                <iframe
                    src="/ss_workflow/procedures/template/{template_id}/flowchart"
                    width="100%"
                    height="720"
                    style="border: 0; border-radius: 12px; background: #ffffff;"
                    loading="lazy">
                </iframe>
            """.format(template_id=rec.id)

    def _get_flowchart_payload(self):
        self.ensure_one()

        tasks = self.tasks.filtered(lambda task: not task.deleted).sorted(key=lambda task: task.id)
        tasks_by_id = {task.id: task for task in tasks}

        node_width = 260
        node_height = 96
        horizontal_gap = 120
        vertical_gap = 150
        padding_x = 80
        padding_y = 56

        edges = []
        seen_edges = set()
        incoming_sources = {task.id: set() for task in tasks}
        outgoing_task_ids = {task.id: set() for task in tasks}
        task_graph = {task.id: [] for task in tasks}
        choice_target_sources = {task.id: set() for task in tasks}
        subprocedure_sources = {}

        def add_edge(source_id, target_id, label=None, choice=False, kind='task'):
            edge_key = (source_id, target_id, label or '', choice, kind)
            if edge_key in seen_edges:
                return

            seen_edges.add(edge_key)
            edges.append({
                'from': source_id,
                'to': target_id,
                'label': label,
                'choice': choice,
                'kind': kind,
            })

        for task in tasks:
            choice_target_ids = set()

            for path_choice in task.path_choices.sorted(key=lambda choice: choice.id):
                target_task = path_choice.target_task_id
                if not target_task or target_task.deleted or target_task.template_id.id != self.id:
                    continue

                choice_target_ids.add(target_task.id)
                incoming_sources[target_task.id].add(task.id)
                outgoing_task_ids[task.id].add(target_task.id)
                if target_task.id not in task_graph[task.id]:
                    task_graph[task.id].append(target_task.id)
                choice_target_sources[target_task.id].add(task.id)
                add_edge(task.id, target_task.id, label=path_choice.name or None, choice=True)

            for next_task in task.next_task_ids.sorted(key=lambda next_rec: next_rec.id):
                if next_task.deleted or next_task.template_id.id != self.id:
                    continue
                if next_task.id in choice_target_ids:
                    continue

                incoming_sources[next_task.id].add(task.id)
                outgoing_task_ids[task.id].add(next_task.id)
                if next_task.id not in task_graph[task.id]:
                    task_graph[task.id].append(next_task.id)
                add_edge(task.id, next_task.id)

            if task.has_procedures:
                for sub_procedure in task.sub_procedures.sorted(key=lambda procedure: procedure.id):
                    subprocedure_id = 'subproc-{0}'.format(sub_procedure.id)
                    source = subprocedure_sources.setdefault(subprocedure_id, {
                        'id': subprocedure_id,
                        'label': sub_procedure.name or 'Untitled Sub Procedure',
                        'template_id': sub_procedure.id,
                        'source_task_ids': set(),
                    })
                    source['source_task_ids'].add(task.id)
                    add_edge(task.id, subprocedure_id, kind='subprocedure')

        graph_levels = {}
        indegree = {task.id: len(incoming_sources[task.id]) for task in tasks}
        start_task_ids = sorted(task.id for task in tasks if not incoming_sources[task.id])
        pending = deque(start_task_ids)
        pending_ids = set(start_task_ids)
        processed = []
        processed_ids = set()

        for task_id in start_task_ids:
            graph_levels[task_id] = 0

        def choice_target_level(target_id):
            source_levels = [
                graph_levels[source_id] + 1
                for source_id in choice_target_sources.get(target_id, set())
                if source_id in graph_levels
            ]
            return max(source_levels) if source_levels else None

        while pending:
            task_id = pending.popleft()
            pending_ids.discard(task_id)
            if task_id in processed_ids:
                continue

            processed.append(task_id)
            processed_ids.add(task_id)
            current_level = graph_levels.get(task_id, 0)

            for target_id in task_graph[task_id]:
                forced_level = choice_target_level(target_id)
                if forced_level is not None:
                    graph_levels[target_id] = forced_level
                else:
                    graph_levels[target_id] = max(graph_levels.get(target_id, 0), current_level + 1)
                indegree[target_id] -= 1
                if indegree[target_id] <= 0 and target_id not in pending_ids and target_id not in processed_ids:
                    pending.append(target_id)
                    pending_ids.add(target_id)

        remaining_task_ids = sorted(task.id for task in tasks if task.id not in processed_ids)
        for task_id in remaining_task_ids:
            forced_level = choice_target_level(task_id)
            if forced_level is not None:
                graph_levels[task_id] = forced_level
            else:
                parent_levels = [graph_levels.get(parent_id, 0) for parent_id in incoming_sources[task_id]]
                graph_levels[task_id] = (max(parent_levels) + 1) if parent_levels else 0
            processed.append(task_id)
            processed_ids.add(task_id)

        tasks_by_level = {}
        for task_id in processed:
            tasks_by_level.setdefault(graph_levels.get(task_id, 0), []).append(tasks_by_id[task_id])

        level_order = sorted(tasks_by_level)

        task_node_meta = {}
        for task in tasks:
            is_start = not incoming_sources[task.id]
            is_end = not outgoing_task_ids[task.id] and bool(incoming_sources[task.id])
            is_choice = bool(task.has_path_choice)

            if is_choice:
                node_type = 'start_choice' if is_start else 'choice'
                tag = 'SEQ {0}'.format(task.task_sequance or 0)
                width = node_width
                height = node_height
            elif is_start:
                node_type = 'start'
                tag = 'SEQ {0}'.format(task.task_sequance or 0)
                width = node_width
                height = node_height
            elif is_end:
                node_type = 'end'
                tag = 'SEQ {0}'.format(task.task_sequance or 0)
                width = node_width
                height = node_height
            else:
                node_type = 'task'
                tag = 'SEQ {0}'.format(task.task_sequance or 0)
                width = node_width
                height = node_height

            badges = []
            if is_start:
                badges.append('Start')
            if is_end:
                badges.append('End')
            if is_choice:
                badges.append('Decision')
            if task.has_procedures:
                badges.append('Sub Procedures: {0}'.format(len(task.sub_procedures)))

            task_node_meta[task.id] = {
                'id': task.id,
                'label': task.name or 'Untitled Task',
                'seq': task.task_sequance or 0,
                'type': node_type,
                'tag': tag,
                'badges': badges,
                'width': width,
                'height': height,
            }

        max_row_width = 0
        for level in level_order:
            row_tasks = tasks_by_level[level]
            row_width = sum(task_node_meta[task.id]['width'] for task in row_tasks)
            row_width += max(len(row_tasks) - 1, 0) * horizontal_gap
            max_row_width = max(max_row_width, row_width)

        inner_width = max(max_row_width, node_width)
        task_world_width = inner_width + (padding_x * 2)

        nodes = []
        for row_index, level in enumerate(level_order):
            row_tasks = tasks_by_level[level]
            row_width = sum(task_node_meta[task.id]['width'] for task in row_tasks)
            row_width += max(len(row_tasks) - 1, 0) * horizontal_gap
            start_x = padding_x + max((inner_width - row_width) / 2, 0)
            cursor_x = start_x

            for task in row_tasks:
                meta = dict(task_node_meta[task.id])
                x = int(cursor_x)
                y = padding_y + (row_index * (node_height + vertical_gap))
                if task.flowchart_position_saved:
                    x = max(int(task.flowchart_x or 0), 0)
                    y = max(int(task.flowchart_y or 0), 0)

                meta.update({
                    'x': x,
                    'y': y,
                    'position_saved': bool(task.flowchart_position_saved),
                })
                nodes.append(meta)

                cursor_x += meta['width'] + horizontal_gap

        subprocedure_node_width = 220
        subprocedure_node_height = 90
        subprocedure_gap = 36
        subprocedure_lane_gap = 180
        subprocedure_nodes = sorted(
            subprocedure_sources.values(),
            key=lambda item: (
                min(graph_levels.get(task_id, 0) for task_id in item['source_task_ids']),
                min(item['source_task_ids']),
                item['label'],
                item['template_id'],
            )
        )

        for index, subprocedure in enumerate(subprocedure_nodes):
            y = padding_y + (index * (subprocedure_node_height + subprocedure_gap))
            nodes.append({
                'id': subprocedure['id'],
                'label': subprocedure['label'],
                'seq': None,
                'x': task_world_width + subprocedure_lane_gap,
                'y': y,
                'type': 'subprocedure',
                'tag': 'SUB PROCEDURE',
                'badges': ['Template #{0}'.format(subprocedure['template_id'])],
                'width': subprocedure_node_width,
                'height': subprocedure_node_height,
            })

        world_width = task_world_width
        if subprocedure_nodes:
            world_width += subprocedure_lane_gap + subprocedure_node_width + padding_x

        task_world_height = (
            (len(level_order) * node_height)
            + (max(len(level_order) - 1, 0) * vertical_gap)
            + (padding_y * 2)
        ) or 600
        subprocedure_world_height = (
            (len(subprocedure_nodes) * subprocedure_node_height)
            + (max(len(subprocedure_nodes) - 1, 0) * subprocedure_gap)
            + (padding_y * 2)
        ) or 0
        world_height = max(task_world_height, subprocedure_world_height, 420)

        if nodes:
            node_max_x = max(node['x'] + node['width'] for node in nodes)
            node_max_y = max(node['y'] + node['height'] for node in nodes)
            world_width = max(world_width, node_max_x + padding_x)
            world_height = max(world_height, node_max_y + padding_y)

        return {
            'template': {
                'id': self.id,
                'name': self.name or '',
                'description': self.description or '',
            },
            'world': {
                'width': int(max(world_width, 600)),
                'height': int(world_height),
            },
            'node_width': node_width,
            'node_height': node_height,
            'nodes': nodes,
            'edges': edges,
        }
            
    enabled                   = fields.Boolean(string='Is Enabled', default=False, tracking=True)
    deleted                   = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    
    def _get_template_domain(self):
        current_user   = self.env.user
        partner_id = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
        groups_can_see = partner_id.groups_can_see.ids  # Get the IDs of the groups
        return [('enabled', '=', True), ('deleted', '=', False), ('default_group', 'in', groups_can_see), ('departments_can_create', 'in', [partner_id.default_department.id])]

    @api.model
    def web_search_read(self, domain=None, offset=0, limit=None, order=None, count_limit=None, specification=None):
        """Override the method called by Odoo's web client"""
        
        # Apply dynamic filtering when the context flag is set
        if self._context.get('apply_dynamic_domain'):
            dynamic_domain = self._get_template_domain()
            domain = domain or []
            domain = AND([domain, dynamic_domain])
        
        # Call the parent method with all parameters
        return super().web_search_read(
            domain=domain, 
            offset=offset, 
            limit=limit, 
            specification=specification, 
            order=order,
            count_limit=count_limit
        )
            
    
    def _set_visibility_to_user(self):
        for record in self:
            current_user   = self.env.user
            partner_id = self.env['ssw.users'].sudo().search([('user_id', '=', current_user.id)], limit=1)
            groups_can_see = partner_id.groups_can_see.ids  # Get the IDs of the groups
            record.visible_to_current_user = False
            if not record.deleted and record.enabled and record.default_group.id in groups_can_see and partner_id.default_department.id in record.departments_can_create.ids:
                record.visible_to_current_user = True
            
    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True

    def _start_procedure_with_values(self, title, description):
        self.ensure_one()
        if not self.visible_to_current_user:
            raise exceptions.ValidationError("You are not allowed to start this proc.")

        procedure = self.env['ssw.procedures'].create({
            "template_id": self.id,
            "name": title,
            "description": description,
        })

        tasks = procedure.tasks
        if tasks and len(tasks) > 0:
            tasks[0].assigned_to_user = self.env.user.partner_id.id
            return {
                'name': 'Task Form',
                'type': 'ir.actions.act_window',
                'res_model': 'ssw.proc.tasks',
                'view_mode': 'form',
                'res_id': tasks[0].id,
                'target': 'current',
            }
        return {
            'name': 'Procedure Form',
            'type': 'ir.actions.act_window',
            'res_model': 'ssw.procedures',
            'view_mode': 'form',
            'res_id': procedure.id,
            'target': 'current',
        }
        
    
    def start_procedure(self):
        self.ensure_one()
        if not self.visible_to_current_user:
            raise exceptions.ValidationError("You are not allowed to start this proc.")
        return {
            'name': 'Start Procedure',
            'type': 'ir.actions.act_window',
            'res_model': 'ssw.proc.start.wizard',
            'view_mode': 'form',
            'view_id': self.env.ref('ss_workflow.view_ssw_proc_start_wizard_form').id,
            'target': 'new',
            'context': {
                'default_template_id': self.id,
                'default_title': self.name,
                'default_description': False,
            },
        }
